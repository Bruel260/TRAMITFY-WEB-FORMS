! function( $ ) {
	"use strict";

	if ( typeof Codevz_Plus != 'object' ) {
		var Codevz_Plus = {};
	}

	Codevz_Plus.woocommerce = function( quantity, timeout ) {

		var body 		= $( document.body ),
			adminBar 	= $( '#wpadminbar' ).length ? 32 : 0,
			cartForm 	= $( '.woocommerce-cart-form__contents' );

		// Fix WC message button.
		$( '.button.wc-forward' ).removeClass( 'wc-forward' );

		// Compare.
		if ( cartForm.length && ! $( '.xtra-cart-td-prefix' ).length ) {

			var cartItem = $( '.cart_item' ),
				productText = cartForm.find( 'th.product-name' ).text(),
				priceText = cartForm.find( 'th.product-price' ).text(),
				quantityText = cartForm.find( 'th.product-quantity' ).text(),
				subTotalText = cartForm.find( 'th.product-subtotal' ).text();

			cartItem.find( 'td.product-name' ).prepend( '<div class="xtra-cart-td-prefix">' + productText + '</div>' );
			cartItem.find( 'td.product-price' ).prepend( '<div class="xtra-cart-td-prefix">' + priceText + '</div>' );
			cartItem.find( 'td.product-quantity' ).prepend( '<div class="xtra-cart-td-prefix">' + quantityText + '</div>' );
			cartItem.find( 'td.product-subtotal' ).prepend( '<div class="xtra-cart-td-prefix">' + subTotalText + '</div>' );

		}

		// Product quantity field.
		if ( $( '.quantity' ).length ) {

			$( '.quantity input' ).each( function() {

				var $this 	= $( this ),
					max 	= $this.attr( 'max' ),
					grouped = $this.closest( '.woocommerce-grouped-product-list-item' ).length;

				if ( ! $this.next( '.quantity-up' ).length ) {

					$( '<div class="quantity-down">-</div>' ).insertBefore( $this );
					$( '<div class="quantity-up">+</div>' ).insertAfter( $this );

					body.off( 'click', '.quantity-up, .quantity-down' ).on( 'click', '.quantity-up, .quantity-down', function() {

						var en = $( this ),
							input = body.hasClass( 'single' ) ? body.find( '.quantity input' ) : en.closest( '.quantity' ).find( 'input' ),
							oldValue = parseInt( input.val() ) || 0;

						if ( oldValue == max && en.hasClass( 'quantity-up' ) ) {
							return false;
						}

						console.log( input );

						input.val( en.hasClass( 'quantity-up' ) ? oldValue + 1 : ( oldValue <= 1 && !grouped ? 1 : ( oldValue == 0 ? 1 : oldValue - 1 ) ) ).trigger( 'change' );

					});

				}

				$this.attr( 'type', 'number' );

			});

			// Update cart fix quantity script.
			body.on( 'updated_cart_totals', function( e ) {

				Codevz_Plus.woocommerce( 2 );

			// When cart page is empty, refresh.
			}).on( 'updated_wc_div', function( e ) {

				if ( ! $( '.cart_item' ).length ) {
					window.location.reload();
				}

			// Auto update cart.
			}).on( 'change', '.woocommerce-cart-form input.qty', function() {

				clearTimeout( timeout );

				timeout = setTimeout( function() {
					$( '[name="update_cart"]' ).trigger( 'click' );
				}, 1000 );

			});

		}

		// Single product page before.
		if ( $( '.variations' ).length ) {

			// Variation lable.
			body.on( 'click', '.codevz-variations label', function() {

				var $this = $( this ),
					radio = $this.prev( 'input' );

				radio.prop( 'checked', true ).parent().find( 'select' ).val( radio.val() ).trigger( 'change' );

			});

		}

		// Only quantity
		if ( quantity ) {
			return;
		}

		// Customize view cart button after click on add to cart.
		body.on( 'added_to_cart', function( event, fragments, cart_hash, button ) {

			setTimeout( function() {

				button.hide().next().addClass( 'button product_type_codevz' );

				setTimeout( function() {

					button.next().fadeIn();

				}, 500 );

			}, 20 );

		});

		// Widgets accordion.
		$( '.codevz-widgets-toggle .page_content .sidebar_inner' ).on( 'click', '.codevz-widget-title', function() {

			$( this ).next( 'div' ).slideToggle();

		});

		// Auto x-position shop cart in header.
		if ( $( '.elms_shop_cart' ).length ) {

			body.on( 'mouseenter', '.elms_shop_cart', function() {

				var cartX 		= $( this ),
					iconX 		= cartX.find( '.shop_icon i' ),
					iconWidth 	= iconX.outerWidth(),
					dropdown  	= cartX.find( '.cz_cart_items' );

				if ( ( $( window ).width() / 2 ) > ( cartX.offset().left + 300 ) ) {

					cartX.addClass( 'inview_right' );

					var iconMl = parseFloat( iconX.css( 'marginLeft' ) );

					if ( body.hasClass( 'rtl' ) ) {
						dropdown.css( 'left', ( ( iconWidth / 2 ) - 38 + iconMl ) );
					} else {
						dropdown.css( 'left', -( ( iconWidth / 2 ) - 36 + iconMl ) );
					}

				} else {

					dropdown.css( 'right', ( ( iconWidth / 2 ) - 36 + parseFloat( iconX.css( 'marginRight' ) ) ) );

				}

			});

			var hoverTimeout;

			$( '.elms_shop_cart' ).on({

			    mouseenter: function() {

			    	clearTimeout( hoverTimeout );

			        var $this = $( this ).find( '.cz_cart_items' );

			        setTimeout( function() {
			            $this.css( 'opacity', 1 );
			        }, 100 );

			    },
			    mouseleave: function() {

			        var $this = $( this ).find( '.cz_cart_items' );

			        $this.css( 'opacity', 1 ).css( 'visibility', 'visible' );

			        hoverTimeout = setTimeout(function() {
			            $this.css( 'opacity', 0 ).css( 'visibility', '' );
			        }, 100 );

			    }
			});

		}

		// Wishlist & Compare popup.
		var wishComPopup = $( '#xtra_wish_compare' );
		wishComPopup.appendTo( 'body' );

		// Fix wishComPopup link issue.
		wishComPopup.on( 'click', 'a', function() {

			window.location = $( this ).attr( 'href' );

		});

		// Current wishlist items
		if ( $( '.xtra-add-to-wishlist' ).length || $( '.xtra-wishlist' ).length || $( '.cz_wishlist_count' ).length ) {

			var wishlist = localStorage.getItem( 'xtraWishlist' ),
				wishlistDiv = $( '.xtra-wishlist' ),
				noWishlist = '<h2 class="xtra-wishlist-empty tac">' + wishlistDiv.attr( 'data-empty' ) + '</h2><a href="' + xtra_strings.shop_url + '" class="button" style="margin:40px auto;display:table;"><span><strong>' + xtra_strings.back_to_shop + '</strong></span></a>';

			// Wishlist shortcode
			wishlistDiv.length && wishlistDiv.each( function() {

				var en 		= $( this ),
					nonce 	= en.data( 'nonce' ),
					col 	= en.data( 'col' );

				if ( wishlist ) {

					// Check ID's.
					$.post( body.data( 'ajax' ), 'action=xtra_wishlist_content&check=1&ids=' + wishlist + '&nonce=' + nonce, function( msg ) {

						localStorage.setItem( 'xtraWishlist', msg );

						wishlist = msg;

						// Get wishlist products.
						$.post( body.data( 'ajax' ), 'action=xtra_wishlist_content&ids=' + wishlist + '&nonce=' + nonce + '&col=' + col, function( msg ) {

							en.removeClass( 'xtra-icon-loading' ).html( msg );

							$( '.woocommerce-loop-product__link' ).prepend( '<i class="xtra-remove-from-wishlist fas fa-times"></i>' );

							var count = localStorage.getItem( 'xtraWishlist' ).replace( /\d+/g,'' ).length;

							// Count
							if ( count ) {
								$( '.cz_wishlist_count' ).show().html( count || '' );
							} else {
								$( '.cz_wishlist_count' ).hide();
								en.removeClass( 'xtra-icon-loading' ).html( noWishlist );
							}

							if ( ! en.find( 'li' ).length ) {
								en.removeClass( 'xtra-icon-loading' ).html( noWishlist );
							}

						});

					});

				} else {

					en.removeClass( 'xtra-icon-loading' ).html( noWishlist );

				}

			});

			// Set wishlist products
			if ( wishlist ) {
				wishlist = wishlist.split( ',' );

				$.each( wishlist, function( index, id ) {
					var product = $( '[data-id="' + id + '"] .xtra-add-to-wishlist' );

					if ( product.length ) {
						product.removeClass( 'fa-heart-o' ).addClass( 'fa-heart' ).attr( 'data-title', xtra_strings.added_wishlist );
					}
				});

				var count = localStorage.getItem( 'xtraWishlist' ).replace( /\d+/g,'' ).length;

				// Count
				if ( count ) {
					$( '.cz_wishlist_count' ).show().html( count );
				} else {
					$( '.cz_wishlist_count' ).hide();
					wishlistDiv.removeClass( 'xtra-icon-loading' ).html( noWishlist );
				}
			}

			// Wishlist icon.
			body.on( 'click', '.xtra-add-to-wishlist,.xtra-remove-from-wishlist', function(e) {

				var en = $( this ),
					id = en.closest( '.product' ).find( '[data-id]' ).data( 'id' ) + ',',
					ls = localStorage.getItem( 'xtraWishlist' ) || '',
					tt = en.attr( 'data-title' );

				if ( en.hasClass( 'fa-heart' ) && ! en.closest( '.xtra-wishlist' ).length ) {

					window.location.replace( xtra_strings.wishlist_url );

				} else {

					en.addClass( 'xtra-icon-loading' ).removeAttr( 'data-title' );

					setTimeout(function() {

						if ( en.hasClass( 'fa-heart' ) || en.hasClass( 'fa-times' ) ) {

							ls = ls.replace( id, '' );

							localStorage.setItem( 'xtraWishlist', ls );

							tt = xtra_strings.add_wishlist;

							en.removeClass( 'fa-heart' ).addClass( 'fa-heart-o' );

						} else if ( ls.indexOf( id ) < 0 ) {

							localStorage.setItem( 'xtraWishlist', ls + id );

							tt = xtra_strings.added_wishlist;

							setTimeout( function() {

								$( '[data-id="' + id.replace( ',', '' ) + '"] .xtra-add-to-wishlist' ).removeClass( 'fa-heart-o' ).addClass( 'fa-heart' ).attr( 'data-title', xtra_strings.added_wishlist );

							}, 500 );

							$( '.cz_close_popup' ).trigger( 'click' );

							setTimeout( function() {

								wishComPopup.find( 'h3' ).html( en.closest( '.product' ).find( '.woocommerce-loop-product__title, .product_title' ).html() );
								wishComPopup.find( 'a' ).attr( 'href', xtra_strings.wishlist_url ).find( 'strong' ).html( xtra_strings.view_wishlist );
								wishComPopup.fadeIn( 'fast' ).delay( 1000 ).addClass( 'cz_show_popup' );

							}, $( '.cz_popup_modal' ).is( ':visible' ) ? 50 : 0 );
						}

						en.removeClass( 'xtra-icon-loading' );

						setTimeout( function() {

							en.attr( 'data-title', tt );

						}, 250 );

						// Wishlist page.
						if ( en.closest( '.xtra-wishlist' ).length ) {

							en.closest( 'li' ).fadeOut(function() {

								$( this ).remove();

								if ( ! wishlistDiv.find( 'li' ).length ) {
									wishlistDiv.removeClass( 'xtra-icon-loading' ).html( noWishlist );
								}

							});

						}

						var count = localStorage.getItem( 'xtraWishlist' ).replace( /\d+/g,'' ).length;

						// Count
						if ( count ) {

							$( '.cz_wishlist_count' ).show().html( count || '' );

						} else {

							$( '.cz_wishlist_count' ).hide();
							wishlistDiv.removeClass( 'xtra-icon-loading' ).html( noWishlist );

						}

					}, 1000 );

				}

				e.preventDefault();
			});

		}

		// Current compare items
		if ( $( '.xtra-add-to-compare' ).length || $( '.xtra-compare' ).length || $( '.cz_compare_count' ).length ) {

			// Compare icon.
			body.on( 'click', '.xtra-add-to-compare,.xtra-remove-from-compare', function(e) {

				var en = $( this ),
					id = en.closest( '.product' ).find( '[data-id]' ).data( 'id' ) + ',',
					ls = localStorage.getItem( 'xtraCompare' ) || '',
					tt = en.attr( 'data-title' );

				// Remove from compare.
				if ( en.attr( 'class' ).indexOf( 'remove' ) > 0 ) {

					id = en.closest( 'td' ).find( '[data-id]' ).data( 'id' ) + ',';

					en.addClass( 'xtra-icon-loading' );

					ls = ls.replace( id, '' );

					localStorage.setItem( 'xtraCompare', ls );

					window.location.replace( xtra_strings.compare_url );

					e.preventDefault();

					return false;

				}

				// ...
				if ( en.hasClass( 'fa-check' ) && ! en.closest( '.xtra-compare' ).length ) {

					window.location.replace( xtra_strings.compare_url );

				} else {

					en.addClass( 'xtra-icon-loading' ).removeAttr( 'data-title' );

					setTimeout( function() {

						if ( en.hasClass( 'fa-check' ) || en.hasClass( 'fa-times' ) ) {

							ls = ls.replace( id, '' );

							localStorage.setItem( 'xtraCompare', ls );

							tt = xtra_strings.add_compare;

							en.removeClass( 'fa-check' ).addClass( 'czico-shuffle' ).attr( 'data-title', xtra_strings.add_compare );

						} else if ( ls.indexOf( id ) < 0 ) {

							localStorage.setItem( 'xtraCompare', ls + id );

							tt = xtra_strings.added_compare;

							setTimeout( function() {

								$( '[data-id="' + id.replace( ',', '' ) + '"] .xtra-add-to-compare' ).removeClass( 'czico-shuffle' ).addClass( 'fa-check' ).attr( 'data-title', xtra_strings.added_compare );

							}, 500 );

							$( '.cz_close_popup' ).trigger( 'click' );

							setTimeout( function() {

								wishComPopup.find( 'h3' ).html( en.closest( '.product' ).find( '.woocommerce-loop-product__title, .product_title' ).html() );
								wishComPopup.find( 'a' ).attr( 'href', xtra_strings.compare_url ).find( 'strong' ).html( xtra_strings.view_compare );
								wishComPopup.fadeIn( 'fast' ).delay( 1000 ).addClass( 'cz_show_popup' );

							}, $( '.cz_popup_modal' ).is( ':visible' ) ? 50 : 0 );

						}

						en.removeClass( 'xtra-icon-loading' );

						setTimeout( function() {

							en.attr( 'data-title', tt );

						}, 250 );

						var compareDiv = null;

						// Compare page.
						if ( en.closest( '.xtra-compare' ).length ) {

							en.closest( 'li' ).fadeOut(function() {

								$( this ).remove();

								if ( ! compareDiv.find( 'li' ).length ) {
									compareDiv.removeClass( 'xtra-icon-loading' ).html( noCompare );
								}

							});

						}

						var count = localStorage.getItem( 'xtraCompare' ).replace( /\d+/g,'' ).length;

						// Count
						if ( count ) {

							$( '.cz_compare_count' ).show().html( count || '' );

						} else {

							$( '.cz_compare_count' ).hide();
							compareDiv.removeClass( 'xtra-icon-loading' ).html( noCompare );

						}

					}, 1000 );

				}

				e.preventDefault();
			});

			var compare = localStorage.getItem( 'xtraCompare' ),
				compareDiv = $( '.xtra-compare' ),
				noCompare = '<h2 class="xtra-compare-empty tac">' + compareDiv.attr( 'data-empty' ) + '</h2><a href="' + xtra_strings.shop_url + '" class="button" style="margin:40px auto;display:table;"><span><strong>' + xtra_strings.back_to_shop + '</strong></span></a>';

			// Compare shortcode
			compareDiv.length && compareDiv.each( function() {

				var en = $( this ),
					nonce = en.data( 'nonce' );

				if ( compare ) {

					// Check ID's.
					$.post( body.data( 'ajax' ), 'action=xtra_compare_content&check=1&ids=' + compare + '&nonce=' + nonce, function( msg ) {

						localStorage.setItem( 'xtraCompare', msg );

						compare = msg;

						// Get compare products.
						$.post( body.data( 'ajax' ), 'action=xtra_compare_content&ids=' + compare + '&nonce=' + nonce, function( msg ) {

							en.removeClass( 'xtra-icon-loading' ).html( msg );

							$( '.cz-compare-tr-general td' ).prepend( '<i class="xtra-remove-from-compare fas fa-times"></i>' );

							var count = localStorage.getItem( 'xtraCompare' ).replace( /\d+/g,'' ).length;

							// Count
							if ( count ) {
								$( '.cz_compare_count' ).show().html( count || '' );
							} else {
								$( '.cz_compare_count' ).hide();
								en.removeClass( 'xtra-icon-loading' ).html( noCompare );
							}

							if ( ! en.find( 'li' ).length ) {
								en.removeClass( 'xtra-icon-loading' ).html( noCompare );
							}

						});

					});

				} else {

					en.removeClass( 'xtra-icon-loading' ).html( noCompare );

				}

			});

			// Set compare products
			if ( compare ) {
				compare = compare.split( ',' );

				$.each( compare, function( index, id ) {
					var product = id ? $( '[data-id="' + id + '"] .xtra-add-to-compare' ) : 0;

					if ( product.length ) {
						product.removeClass( 'czico-shuffle' ).addClass( 'fa-check' ).attr( 'data-title', xtra_strings.added_compare );
					}
				});

				var count = localStorage.getItem( 'xtraCompare' ).replace( /\d+/g,'' ).length;

				// Count
				if ( count ) {
					$( '.cz_compare_count' ).show().html( count );
				} else {
					$( '.cz_compare_count' ).hide();
					compareDiv.removeClass( 'xtra-icon-loading' ).html( noCompare );
				}
			}

		}

		// Product quick view.
		if ( $( '.xtra-product-quick-view' ).length ) {

			body.on( 'click', '.xtra-product-quick-view', function( e ) {

				e.preventDefault();

				var x = $( this ),
					id = x.parent().data( 'id' ),
					nonce = x.data( 'nonce' ),
					popup = $( '#xtra_quick_view' ),
					content = popup.find( '.cz_popup_in > div' ),
					tt = x.attr( 'data-title' );

				popup.appendTo( 'body' );

				x.addClass( 'xtra-icon-loading' ).removeAttr( 'data-title' );

				content.html( '' );
				content.addClass( 'xtra-qv-loading' );

				popup.fadeIn( 'fast' ).delay( 1000 ).addClass( 'cz_show_popup' );

				$( 'html, body' ).addClass( 'no-scroll' );

				$.post( body.data( 'ajax' ), 'action=xtra_quick_view&id=' + id + '&nonce=' + nonce, function( msg ) {

					x.removeClass( 'xtra-icon-loading' ).attr( 'data-title', tt );
					content.removeClass().html( msg );

					// Set wishlist in quickview.
					if ( localStorage.getItem( 'xtraWishlist' ) && localStorage.getItem( 'xtraWishlist' ).indexOf( id ) >= 0 ) {

						$( '[data-id="' + id + '"] .xtra-add-to-wishlist' ).removeClass( 'fa-heart-o' ).addClass( 'fa-heart' ).attr( 'data-title', xtra_strings.added_wishlist );

					}

					// Set compare in quickview.
					if ( localStorage.getItem( 'xtraCompare' ) && localStorage.getItem( 'xtraCompare' ).indexOf( id ) >= 0 ) {

						$( '[data-id="' + id + '"] .xtra-add-to-compare' ).removeClass( 'czico-shuffle' ).addClass( 'fa-check' ).attr( 'data-title', xtra_strings.added_compare );

					}

					// Fix flex slider.
					setTimeout( function() {

						content.find( '.woocommerce-product-gallery' ).each( function() {
							$( this ).wc_product_gallery();
						} );

						$( window ).trigger( 'resize' );

					}, 500 );

					// Add to cart redirect to single product on quick view popup. 
					var $button = content.find( '[name="add-to-cart"]' );
					$button.closest( 'form' ).attr( 'action', xtra_strings.shop_url + '?p=' + $button.attr( 'value' ) );

					Codevz_Plus.woocommerce( 1 );

				});

				return false;

			});

		}

		// Append onsale badge to parent.
		$( 'ul.products .onsale' ).codevz( 'onsale', function( x ) {

			x.appendTo( x.closest( 'a' ) );

		});

		// Checkout page functions.
		if ( body.hasClass( 'woocommerce-checkout' ) ) {

			// Fix checkout section title!
			$( 'wc-order-attribution-inputs' ).each( function() {

				var $this = $( this );

				$this.appendTo( $this.parent() );

			});

			// Fix City and state in RTL.
			if ( body.hasClass( 'rtl' ) ) {

				setTimeout( function() {

					$( '#billing_state_field' ).insertBefore( '#billing_city_field' );

				}, 500 );

			}

		}

		// Add button class to restore-item link in message box.
		$( '.woocommerce-message .restore-item' ).addClass( 'button' );

		// Added to cart notification.
		body.on( 'added_to_cart', function( e, fragments, cart_hash, $button ) {

			var notif = $( '.codevz-added-to-cart-notif' ),
				width = notif.find( 'span' ).outerWidth();

			// Check if its on single page with sticky mode.
			if ( $( '.cz-sticky-add-to-cart' ).length ) {

				notif.addClass( 'codevz-added-to-cart-notif-above-sticky' );

			}

			setTimeout( function() {

				var title = $button.parent().find( '.woocommerce-loop-product__title' ).html();

				// Show the notification.
				notif.addClass( 'codevz-added-to-cart-notif-show' ).find( 'strong' ).text( title.replace( /<[^>]*>.*?<\/[^>]*>/g, '' ) );

				setTimeout( function() {

					// Show the added details span.
					notif.addClass( 'codevz-added-to-cart-notif-show-span' );

				}, 1200 );

				setTimeout( function() {

					// Hide the notification after a delay.
					notif.removeClass( 'codevz-added-to-cart-notif-show-span' );

					setTimeout( function() {

						// Hide the span.
						notif.removeClass( 'codevz-added-to-cart-notif-show' );

					}, 500 );

				}, 6000 );

			}, 100 );

		});

		// Single product page.
		if ( body.hasClass( 'single-product' ) ) {

			setTimeout( function() {

				// Sticky product tabs row.
				var woot 	= $( '.codevz-sticky-product-tabs .woocommerce-tabs' ),
					tabs 	= woot.find( 'ul.tabs' ),
					panels 	= woot.find( '.panel' ),
					lastTab = panels.last(),
					scrollTop, isFixed, activeTabId;

				if ( tabs.length ) {

					var tabsOffset  = tabs.offset().top,
						placeholder = $( '<div>' ).css({
							'height': tabs.outerHeight(),
							'margin': tabs.css( 'margin' )
						}).insertBefore( tabs );

					$( window ).on( 'scroll', function() {

						if ( Codevz.inview( woot, 100 ) ) {

							requestAnimationFrame(function() {

								scrollTop 	= $(window).scrollTop() + adminBar;
								isFixed 	= scrollTop > tabsOffset;

								tabs.toggleClass( 'fixed-tabs', isFixed );
								placeholder[ isFixed ? 'show' : 'hide' ]();

								// Determine which tab content is currently in view
								panels.each( function() {
									var tabContent 		= $(this);
									var topOffset 		= tabContent.offset().top - 150;
									var bottomOffset 	= topOffset + tabContent.outerHeight();

									// Check if the tab content is within the viewport
									if ( topOffset <= ( scrollTop + tabs.outerHeight()) && bottomOffset >= scrollTop ) {
										activeTabId = tabContent.attr('id');
										return false;
									}
								});

								// Set active class on corresponding tab link
								tabs.find('li').removeClass('active');
								if ( activeTabId ) {
									tabs.find('a[href="#' + activeTabId + '"]').closest('li').addClass('active');
								} else if ( scrollTop <= tabsOffset ) { // If at top of page, set first tab as active
									tabs.find('li:first').addClass('active');
								}

								// Last tab out of viewport, hide sticky.
								tabs.toggle( ( lastTab.offset().top + lastTab.outerHeight() - tabs.outerHeight() ) >= scrollTop );

							});

						}

					});

				}

				// Tabs scroll to.
				body.on( 'click', '.wc-tabs a', function() {

					var $page 		= $( 'html, body' ),
						tabs 		= $( 'body:not(.woo-product-tabs-vertical) .wc-tabs' ),
						tab 		= $( this ).attr( 'href' ).replace( '#', '' ),
						sticky 		= $( '.header_is_sticky' ).not( '.smart_sticky,.header_4' );

					$page.animate({

						scrollTop: $( '#' + tab ).offset().top - ( tabs.outerHeight() || 0 ) - ( sticky.outerHeight() || 0 ) - adminBar - ( $( '.xtra-preview-header' ).outerHeight() || 0 ) - 50

					}, 1000, 'easeInOutCodevz', function() {

						$page.stop();

					});

				});

			}, 1000 );

			// Disable product page lightbox
			$( '.woo-disable-lightbox .woocommerce-product-gallery__wrapper > div:first-child a' ).removeAttr( 'href' ).css( 'cursor', 'default' );

			// Move single onSale badge to sticky.
			$( '.xtra-single-product' ).prev( '.onsale' ).prependTo( $( '.xtra-single-product > div:first-child' ) );

			// Woo sticky column.
			$( '.woocommerce-product-gallery' ).addClass( 'cz_sticky_col' );

			// Edit reviews count in tab and add span.
			$( '.wc-tabs [href="#tab-reviews"]' ).html( function( i, html ) {
				return html.replace( '(', '<span>' ).replace( ')', '</span>' );
			});

			// Single add to cart fake loading.
			body.on( 'click', '.cart .button.single_add_to_cart_button', function() {

				$( this ).addClass( 'loading' );

			});

			// Out of stock message.
			var outofstock_p = $( 'p.stock.out-of-stock' );

			// Add error class to outofstock p tag.
			outofstock_p.addClass( 'woocommerce-error' );

			// Sticky add to cart.
			if ( $( '.cz-sticky-add-to-cart' ).length && ! outofstock_p.length ) {

				var sticky 	= $( '.cz-sticky-add-to-cart .row' ),
					product = $( '.xtra-single-product' ),
					cart 	= product.find( '.cart' ),
					footer  = $( '#site_footer' );

				// Set product details in sticky bar.
				sticky.append( '<h3>' + product.find( '.product_title' ).html() + '</h3>' );
				sticky.append( product.find( '.cart' ).clone() );
				sticky.find( 'h3' ).append( product.find( '.price' ).clone() );

				// Change add to cart to select options for variation products.
				if ( sticky.find( '.variations_form' ).length && ! $( '.codevz-select-options' ).length ) {

					sticky.find( '.single_add_to_cart_button' ).hide().after( '<a href="#" class="codevz-select-options button alt">' + xtra_strings.select_options + '</a>' );

					body.on( 'click', '.codevz-select-options', function() {

						var targetOffsetTop = $( '.summary .variations_form' ).offset().top - 100;

						$( 'html, body' ).animate( { scrollTop: targetOffsetTop }, 1200, 'easeInOutCodevz' );

						return false;

					});

				}

				// Product image.
				var image = product.find( '.woocommerce-product-gallery__image' ).attr( 'data-thumb' ),
					offset;

				if ( image ) {
					sticky.prepend( '<img src="' + image + '" />' );
				}

				// Show/hide sticky on scroll.
				$( window ).on( 'scroll.sticky_add_to_cart', function() {

					clearTimeout( timeout );

					timeout = setTimeout( function() {

						offset = $( window ).scrollTop() > cart.offset().top + cart.outerHeight();

						sticky.parent()[ offset ? 'addClass' : 'removeClass' ]( 'cz-sticky-add-to-cart-show' );

						// Fix footer margin.
						!footer.hasClass( 'woo-sticky-footer-done' ) && footer.addClass( 'woo-sticky-footer-done' ).css( 'margin-bottom', sticky.parent().outerHeight() );

						// Fix fixed icons.
						$( 'i.backtotop,i.fixed_contact' ).css( 'bottom', ( offset ? ( $( window ).width() > 769 ? $( '.cz-sticky-add-to-cart' ).outerHeight() + 20 : '' ) : '' ) );

					}, 100 );

				});

			} // Sticky add to cart.

			// After gallery carousel init.
			Codevz.heightChanged( $( '.woocommerce-product-gallery' ), function() {

				if ( ! $( '.codevz-woo-zoom' ).length ) {

					// Add title to product image zoom icon.
					$( '.woocommerce-product-gallery__trigger' ).html( '<span class="codevz-woo-zoom">' + xtra_strings.zoom_text + '</span>' );

					// Append onsale badge to parent.
					$( 'div.product, .xtra-single-product' ).find( '> .onsale' ).appendTo( $( '.woocommerce-product-gallery__trigger' ).parent() );

				}

			});

		} // Single product page.

	};

	Codevz_Plus.woocommerce();

}( jQuery );