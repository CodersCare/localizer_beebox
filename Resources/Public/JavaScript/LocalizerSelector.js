/**
 * Module: TYPO3/CMS/Localizer/LocalizerSelector
 */
define(['jquery', 'bootstrap'], function ($) {
	'use strict';

	/**
	 * The localizer selector object
	 *
	 * @type {{}}
	 * @exports TYPO3/CMS/Localizer/LocalizerSelector
	 */
	var LocalizerSelector = {
		initialize: function () {
		}
	};

	/**
	 * Initialize
	 */
	LocalizerSelector.initialize = function (selector) {
		$('body').append($('#t3-modal-finalizecart'));
		$('#finalize-cart-submit').click(function (e) {
			e.preventDefault();
            $(this).off("click").attr('href', "javascript: void(0);");
			$('#configuratorFinalize').val('finalize');
			$('#localizer_selector').submit();
		});
		$('.localizer-matrix-configurator .dropdown-menu li .small').click(function () {
			$(this).find('input').prop('checked', !$(this).find('input').prop('checked'));
			return false;
		});
		$('.localizer-matrix-configurator .dropdown-menu li .small input').click(function (event) {
			event.stopPropagation();
		});
		$('.localizer-selector-matrix .language-header .btn').click(function (event) {
			event.stopPropagation();
			event.preventDefault();
			$('[data-toggle="tooltip"]').tooltip('hide');
			var children = null;
			$(this).toggleClass('active');
			if ($(this).hasClass('active')) {
				children = $('.language-record-marker:nth-child(' + ($(this).closest('.language-header').index() + 1) + ') .btn').not('.active');
				children.click();
				children.find('input').prop('checked', true);
			} else {
				children = $('.language-record-marker:nth-child(' + ($(this).closest('.language-header').index() + 1) + ') .btn.active');
				children.click();
				children.find('input').prop('checked', false);
			}
		});
		$('.localizer-selector-matrix .language-header').click(function (event) {
			event.stopPropagation();
			$(this).find('.btn').click();
		});
		$('.localizer-selector-matrix .record-header .btn').click(function (event) {
			event.stopPropagation();
			event.preventDefault();
			$('[data-toggle="tooltip"]').tooltip('hide');
			$(this).toggleClass('active');
			var relatedRecords = $('.parent-' + $(this).data('tableid'));
			var children = null;
			if ($(this).hasClass('active')) {
				relatedRecords.find('.record-header .btn').addClass('active');
				children = relatedRecords.find('.language-record-marker .btn').not('.active');
				children.click();
				children.find('input').prop('checked', true);
			} else {
				relatedRecords.find('.record-header .btn').removeClass('active');
				children = relatedRecords.find('.language-record-marker .btn.active');
				children.click();
				children.find('input').prop('checked', false);
			}
		});
		var recordHeader = $('.localizer-selector-matrix .record-header');
		recordHeader.click(function (event) {
			event.stopPropagation();
			$(this).find('.btn').click();
		});
		$('.localizer-selector-matrix .language-record-marker .btn').mouseup(function (event) {
			event.preventDefault();
			$('[data-toggle="tooltip"]').tooltip('hide');
			var children = null;
			if ($(this).hasClass('active')) {
				children = $('.parent-' + $(this).closest('tr').find('.record-header .btn').data('tableid')).find('.language-record-marker:nth-child(' + ($(this).closest('.language-record-marker').index() + 1) + ') .btn.active').not(this);
				children.click();
				children.find('input').prop('checked', false);
			} else {
				children = $('.parent-' + $(this).closest('tr').find('.record-header .btn').data('tableid')).find('.language-record-marker:nth-child(' + ($(this).closest('.language-record-marker').index() + 1) + ') .btn').not('.active').not(this);
				children.click();
				children.find('input').prop('checked', true);
			}
		});
		$('.localizer-selector-matrix .column-hover').hover(function () {
			$('.language-record-marker').removeClass('hover');
			$('.language-record-marker:nth-child(' + ($(this).index() + 1) + ')').addClass('hover');
		});
		recordHeader.hover(function () {
			$('.language-record-marker').removeClass('hover');
		});
		$('.localizer-selector-matrix').hover(
				function () {
				},
				function () {
					$('.language-record-marker').removeClass('hover');
				}
		);
		if ($('.t3js-clearable').length) {
			require(['TYPO3/CMS/Backend/jquery.clearable'], function () {
				$('.t3js-clearable').clearable();
			});
		}
	};

	$(function () {
		LocalizerSelector.initialize();
	});

	// expose as global object
	TYPO3.LocalizerSelector = LocalizerSelector;

	return LocalizerSelector;
});
