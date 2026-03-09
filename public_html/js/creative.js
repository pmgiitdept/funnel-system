$(document).ready(function () {
	"use strict"; // Start of use strict

	var carousel = $(".content-carousel").waterwheelCarousel({
			flankingItems: 3,
	 		autoPlay: -800,
			separation: 200,
			sizeMultiplier: 0.9,
			horizonOffset: 10}), 
		navbarCollapse = function() { // Collapse Navbar
			if ($("#mainNav").offset().top > 100) {
				$("#mainNav").addClass("navbar-shrink");
			} else {
				$("#mainNav").removeClass("navbar-shrink");
			}};

	// Smooth scrolling using jQuery easing
	$('a.js-scroll-trigger[href*="#"]:not([href="#"])').click(function() {
		if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
			var target = $(this.hash), htnav;
			target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
			if (target.length) {
				htnav = ($("#mainNav").height() == 100 ? 117 : 77);
		  		$('html, body').animate({scrollTop: (target.offset().top - htnav)}, 1000, "easeInOutExpo");
				return false;
			}
		}
	});

	// Closes responsive menu when a scroll trigger link is clicked
	$('.js-scroll-trigger').click(function() {
		$('.navbar-collapse').collapse('hide');
	});

	// Activate scrollspy to add active class to navbar items on scroll
	$('body').scrollspy({
		target: '#mainNav',
		offset: 120
	});

	// Collapse now if page is not at top
	navbarCollapse();
	// Collapse the navbar when page is scrolled
	$(window).scroll(navbarCollapse);
	$(window).resize(function() {
		var newOption;

		if($(window).width() >= 992) {
			newOption = eval("({ flankingItems: 3, autoPlay: -800, separation: 200, sizeMultiplier: 0.9, horizonOffset: 10 })");
		} else {
			newOption = eval("({ flankingItems: 2, autoPlay: -800, separation: 100, sizeMultiplier: 0.9, horizonOffset: 10 })");
			// alert(newOption);
		}
		carousel.reload(newOption);
		location.reload(true);
	});
	// Scroll reveal calls
	// window.sr = ScrollReveal();

	// sr.reveal('.sr-icon-1', {
	// 	delay: 200,
	// 	scale: 0
	// });
	// sr.reveal('.sr-icon-2', {
	// 	delay: 400,
	// 	scale: 0
	// });
	// sr.reveal('.sr-icon-3', {
	// 	delay: 600,
	// 	scale: 0
	// });
	// sr.reveal('.sr-icon-4', {
	// 	delay: 800,
	// 	scale: 0
	// });
	// sr.reveal('.sr-button', {
	// 	delay: 200,
	// 	distance: '15px',
	// 	origin: 'bottom',
	// 	scale: 0.8
	// });
	// sr.reveal('.sr-contact-1', {
	// 	delay: 200,
	// 	scale: 0
	// });
	// sr.reveal('.sr-contact-2', {
	// 	delay: 400,
	// 	scale: 0
	// });
});