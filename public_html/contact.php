<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

const CONTACT_RATE_LIMIT_FILE = __DIR__ . '/.contact_rate_limit.json';
const CONTACT_MAIL_LOG_FILE = __DIR__ . '/.contact_mail.log';
const CONTACT_ENV_FILE = __DIR__ . '/../.env';
const CONTACT_COOLDOWN_SECONDS = 120;
const CONTACT_MAX_SUBMISSIONS_PER_HOUR = 5;
const CONTACT_RATE_WINDOW_SECONDS = 3600;
const CONTACT_MIN_MESSAGE_LENGTH = 10;

function redirectWithAlert($message)
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo "<script>
        alert('{$safeMessage}');
        window.location.href = 'index.html';
    </script>";
    exit;
}

function getClientIpAddress()
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function loadEnvironmentConfig($filePath)
{
    if (!file_exists($filePath)) {
        return [];
    }

    $config = parse_ini_file($filePath, false, INI_SCANNER_RAW);

    return is_array($config) ? $config : [];
}

function logContactMailError($message)
{
    $timestamp = date('Y-m-d H:i:s');

    file_put_contents(CONTACT_MAIL_LOG_FILE, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function ensureRateLimitFileExists()
{
    if (!file_exists(CONTACT_RATE_LIMIT_FILE)) {
        file_put_contents(CONTACT_RATE_LIMIT_FILE, json_encode(new stdClass()));
    }
}

function checkRateLimit($ipAddress)
{
    $now = time();
    $rateLimitData = [];

    ensureRateLimitFileExists();

    $fileHandle = fopen(CONTACT_RATE_LIMIT_FILE, 'c+');
    if ($fileHandle === false) {
        return true;
    }

    try {
        if (!flock($fileHandle, LOCK_EX)) {
            fclose($fileHandle);
            return true;
        }

        $fileContents = stream_get_contents($fileHandle);
        if ($fileContents !== false && trim($fileContents) !== '') {
            $decodedData = json_decode($fileContents, true);
            if (is_array($decodedData)) {
                $rateLimitData = $decodedData;
            }
        }

        foreach ($rateLimitData as $trackedIp => $timestamps) {
            $filteredTimestamps = array_values(array_filter((array) $timestamps, function ($timestamp) use ($now) {
                return is_numeric($timestamp) && ($now - (int) $timestamp) < CONTACT_RATE_WINDOW_SECONDS;
            }));

            if (empty($filteredTimestamps)) {
                unset($rateLimitData[$trackedIp]);
                continue;
            }

            $rateLimitData[$trackedIp] = $filteredTimestamps;
        }

        $ipTimestamps = $rateLimitData[$ipAddress] ?? [];
        $lastSubmission = empty($ipTimestamps) ? null : (int) end($ipTimestamps);

        if ($lastSubmission !== null && ($now - $lastSubmission) < CONTACT_COOLDOWN_SECONDS) {
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
            return false;
        }

        if (count($ipTimestamps) >= CONTACT_MAX_SUBMISSIONS_PER_HOUR) {
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
            return false;
        }

        $ipTimestamps[] = $now;
        $rateLimitData[$ipAddress] = $ipTimestamps;

        rewind($fileHandle);
        ftruncate($fileHandle, 0);
        fwrite($fileHandle, json_encode($rateLimitData));

        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);

        return true;
    } catch (Throwable $exception) {
        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);
        return true;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

$honeypot = trim($_POST['website'] ?? '');
if ($honeypot !== '') {
    redirectWithAlert('Message could not be sent. Please try again.');
}

$submittedAt = isset($_POST['submitted_at']) ? (int) $_POST['submitted_at'] : 0;
if ($submittedAt > 0 && (time() - $submittedAt) < 3) {
    redirectWithAlert('Please wait a moment before sending your message.');
}

$name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$subjectLine = htmlspecialchars(trim($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $subjectLine === '' || $message === '') {
    redirectWithAlert('Please complete all required fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithAlert('Please enter a valid email address.');
}

if (mb_strlen($message) < CONTACT_MIN_MESSAGE_LENGTH) {
    redirectWithAlert('Please enter a longer message before sending.');
}

if (!checkRateLimit(getClientIpAddress())) {
    redirectWithAlert('Too many messages were sent from your network. Please try again later.');
}

$envConfig = loadEnvironmentConfig(CONTACT_ENV_FILE);
$smtpUsername = trim($envConfig['SMTP_USERNAME'] ?? '');
$smtpPassword = preg_replace('/\s+/', '', trim($envConfig['SMTP_PASSWORD'] ?? ''));
$smtpHost = trim($envConfig['SMTP_HOST'] ?? 'smtp.gmail.com');
$smtpPort = (int) ($envConfig['SMTP_PORT'] ?? 587);
$smtpSecure = strtolower(trim($envConfig['SMTP_SECURE'] ?? ($smtpPort === 465 ? 'ssl' : 'tls')));
$smtpRecipient = trim($envConfig['SMTP_TO_EMAIL'] ?? $smtpUsername);

if ($smtpUsername === '' || $smtpPassword === '' || $smtpRecipient === '') {
    logContactMailError('SMTP config is incomplete. Check SMTP_USERNAME, SMTP_PASSWORD, and SMTP_TO_EMAIL.');
    redirectWithAlert('Email service is not configured yet. Please contact the site administrator.');
}

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = $smtpSecure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtpPort;

    $mail->setFrom($mail->Username, 'PMGI Website');
    $mail->addAddress($smtpRecipient);
    $mail->addReplyTo($email, $name);

    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

    $mail->isHTML(true);
    $mail->Subject = "New Website Inquiry: $subjectLine";
    $mail->Body = "
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Subject:</strong> $subjectLine</p>
        <hr>
        <p><strong>Message:</strong><br>{$safeMessage}</p>
    ";

    $mail->send();
    redirectWithAlert('Message sent successfully!');
} catch (Exception $e) {
    logContactMailError("PHPMailer failed: {$e->getMessage()} | ErrorInfo: {$mail->ErrorInfo}");
    redirectWithAlert('Message could not be sent right now. Please try again later.');
}
