/* jshint onevar: false, smarttabs: true */
/* global sharing_loading_icon */

(function($) {
	$( document ).ready(function() {
		function enable_share_button() {
			$( '.preview a.sharing-anchor' ).unbind( 'mouseenter mouseenter' ).hover( function() {
				if ( $( this ).data( 'hasappeared' ) !== true ) {
					var item     = $( '.sharing-hidden .inner' );
					var original = $( this ).parents( 'li' );

					// Create a timer to make the area appear if the mouse hovers for a period
					var timer = setTimeout( function() {
						$( item ).css( {
							left: $( original ).position().left + 'px',
							top: $( original ).position().top + $( original ).height() + 3 + 'px'
						} ).slideDown( 200, function() {
							// Mark the item as have being appeared by the hover
							$( original ).data( 'hasappeared', true ).data( 'hasoriginal', true ).data( 'hasitem', false );

							// Remove all special handlers
							$( item ).mouseleave( handler_item_leave ).mouseenter( handler_item_enter );
							$( original ).mouseleave( handler_original_leave ).mouseenter( handler_original_enter );

							// Add a special handler to quickly close the item
							$( original ).click( close_it );
						} );

						// The following handlers take care of the mouseenter/mouseleave for the share button and the share area - if both are left then we close the share area
						var handler_item_leave = function() {
							$( original ).data( 'hasitem', false );

							if ( $( original ).data( 'hasoriginal' ) === false ) {
								var timer = setTimeout( close_it, 800 );
								$( original ).data( 'timer2', timer );
							}
						};

						var handler_item_enter = function() {
							$( original ).data( 'hasitem', true );
							clearTimeout( $( original ).data( 'timer2' ) );
						};

						var handler_original_leave = function() {
							$( original ).data( 'hasoriginal', false );

							if ( $( original ).data( 'hasitem' ) === false ) {
								var timer = setTimeout( close_it, 800 );
								$( original ).data( 'timer2', timer );
							}
						};

						var handler_original_enter = function() {
							$( original ).data( 'hasoriginal', true );
							clearTimeout( $( original ).data( 'timer2' ) );
						};

						var close_it = function() {
							item.slideUp( 200 );

							// Clear all hooks
							$( original ).unbind( 'mouseleave', handler_original_leave ).unbind( 'mouseenter', handler_original_enter );
							$( item ).unbind( 'mouseleave', handler_item_leave ).unbind( 'mouseenter', handler_item_leave );
							$( original ).data( 'hasappeared', false );
							$( original ).unbind( 'click', close_it );
							return false;
						};
					}, 200 );

					// Remember the timer so we can detect it on the mouseout
					$( this ).data( 'timer', timer );
				}
			}, function() {
				// Mouse out - remove any timer
				clearTimeout( $( this ).data( 'timer' ) );
				$( this ).data( 'timer', false );
			} );
		}

		function update_preview() {
			var button_style = $( '#button_style' ).val();

			// Clear the live preview
			$( '#live-preview ul.preview li' ).remove();

			// Add label
			if ( $( '#save-enabled-shares input[name=visible]' ).val() || $( '#save-enabled-shares input[name=hidden]' ).val() ) {
				$( '#live-preview ul.preview' ).append( $( '#live-preview ul.archive .sharing-label' ).clone() );
			}

			// Re-insert all the enabled items
			$( 'ul.services-enabled li' ).each( function() {
				if ( $( this ).hasClass( 'service' ) ) {
					var service = $( this ).attr( 'id' );
					$( '#live-preview ul.preview' ).append( $( '#live-preview ul.archive li.preview-' + service ).clone() );
				}
			} );

			// Add any hidden items
			if ( $( '#save-enabled-shares input[name=hidden]' ).val() ) {
				// Add share button
				$( '#live-preview ul.preview' ).append( $( '#live-preview ul.archive .share-more' ).parent().clone() );

				$( '.sharing-hidden ul li' ).remove();

				// Add hidden items into the inner panel
				$( 'ul.services-hidden li' ).each( function( /*pos, item*/ ) {
					if ( $( this ).hasClass( 'service' ) ) {
						var service = $( this ).attr( 'id' );
						$( '.sharing-hidden .inner ul' ).append( $( '#live-preview ul.archive .preview-' + service ).clone() );
					}
				} );

				enable_share_button();
			}

			$( '#live-preview div.sharedaddy' ).removeClass( 'sd-social-icon' );
			$( '#live-preview li.advanced' ).removeClass( 'no-icon' );

			// Button style
			if ( 'icon' === button_style ) {
				$( '#live-preview ul.preview div span, .sharing-hidden .inner ul div span' ).html( '&nbsp;' ).parent().addClass( 'no-text' );
				$( '#live-preview div.sharedaddy' ).addClass( 'sd-social-icon' );
			} else if ( 'official' === button_style ) {
				$( '#live-preview ul.preview .advanced, .sharing-hidden .inner ul .advanced' ).each( function( /*i*/ ) {
					if ( !$( this ).hasClass( 'preview-press-this' ) && !$( this ).hasClass( 'preview-email' ) && !$( this ).hasClass( 'preview-print' ) && !$( this ).hasClass( 'share-custom' ) ) {
						$( this ).find( '.option a span' ).html( '' ).parent().removeClass( 'sd-button' ).parent().attr( 'class', 'option option-smart-on' );
					}
				} );
			} else if ( 'text' === button_style ) {
				$( '#live-preview li.advanced' ).addClass( 'no-icon' );
			}

		}

		window.sharing_option_changed = function() {
			var item = this;

			// Loading icon
			$( this ).parents( 'li:first' ).css( 'backgroundImage', 'url("' + sharing_loading_icon + '")' );

			// Save
			$( this ).parents( 'form' ).ajaxSubmit( function( response ) {
				if ( response.indexOf( '<!---' ) >= 0 ) {
					var button = response.substring( 0, response.indexOf( '<!--->' ) );
					var preview = response.substring( response.indexOf( '<!--->' ) + 6 );

					if ( $( item ).is( ':submit' ) === true ) {
						// Update the DOM using a bit of cut/paste technology

						$( item ).parents( 'li:first' ).replaceWith( button );
					}

					$( '#live-preview ul.archive li.preview-' + $( item ).parents( 'form' ).find( 'input[name=service]' ).val() ).replaceWith( preview );
				}

				// Update preview
				update_preview();

				// Restore the icon
				$( item ).parents( 'li:first' ).removeAttr( 'style' );
			} );

			if ( $( item ).is( ':submit' ) === true ) {
				return false;
			}
			return true;
		};

		function showExtraOptions( service ) {
			jQuery( '.' + service + '-extra-options' ).css( { backgroundColor: '#ffffcc' } ).fadeIn();
		}

		function hideExtraOptions( service ) {
			jQuery( '.' + service + '-extra-options' ).fadeOut( 'slow' );
		}

		function save_services() {
			$( '#enabled-services h3 img' ).show();

			// Toggle various dividers/help texts
			if ( $( '#enabled-services ul.services-enabled li.service' ).length > 0 ) {
				$( '#drag-instructions' ).hide();
			}
			else {
				$( '#drag-instructions' ).show();
			}

			if ( $( '#enabled-services li.service' ).length > 0 ) {
				$( '#live-preview .services h2' ).hide();
			}
			else {
				$( '#live-preview .services h2' ).show();
			}

			// Gather the modules
			var visible = [], hi