<?php

namespace Ainsys\Connector\Woocommerce\Webhooks;

use Ainsys\Connector\Master\Hooked;
use Ainsys\Connector\Master\Webhook_Handler;

class Handle_Product implements Hooked, Webhook_Handler {

	public function __construct() {
	}

	/**
	 * Initializes WordPress hooks for component.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_filter( 'ainsys_webhook_action_handlers', array( $this, 'register_webhook_handler' ), 10, 1 );
	}

	public function register_webhook_handler( $handlers = array() ) {
		$handlers['product'] = array( $this, 'handler' );

		return $handlers;
	}

	/**
	 * @throws \WC_API_Exception
	 * @throws \WC_Data_Exception
	 */
	public function handler( $action, $data, $object_id = 0 ) {

		switch ( $action ) {
			case 'add':
				$product_id = wp_insert_post( [ 'post_type' => 'product', 'post_title' => $data['title'] ] );

				return $this->update_product( $data, $product_id );
			case 'update':
				return $this->update_product( $data, $object_id );
			case 'delete':
				$WC_Product = new \WC_Product( $object_id );

				return $WC_Product->delete();
		}

		return 'Action not registered';
	}

	/**
	 * @param $data
	 * @param $object_id
	 *
	 * @return int|string
	 * @throws \WC_API_Exception
	 * @throws \WC_Data_Exception
	 */
	private function update_product( $data, $object_id ) {
		$data       = (array) $data;
		$data['id'] = $object_id;

		if ( ! wc_get_product( $object_id ) ) {
			return 'Товар не найден';
		}

		$product = new \WC_Product( $data["id"] );

		// Title
		if ( isset( $data['title'] ) ) {
			wp_update_post( array( 'ID' => $product->get_id(), 'post_title' => $data['title'] ) );
		}

		// Title
		if ( isset( $data['content'] ) ) {
			wp_update_post( array( 'ID' => $product->get_id(), 'post_content' => $data['content'] ) );
		}

		// Virtual
		if ( isset( $data['virtual'] ) ) {
			$product->set_virtual( $data['virtual'] );
		}

		// Tax status
		if ( isset( $data['tax_status'] ) ) {
			$product->set_tax_status( wc_clean( $data['tax_status'] ) );
		}

		// Tax Class
		if ( isset( $data['tax_class'] ) ) {
			$product->set_tax_class( wc_clean( $data['tax_class'] ) );
		}

		// Catalog Visibility
		if ( isset( $data['catalog_visibility'] ) ) {
			$product->set_catalog_visibility( wc_clean( $data['catalog_visibility'] ) );
		}

		// Purchase Note
		if ( isset( $data['purchase_note'] ) ) {
			$product->set_purchase_note( wc_clean( $data['purchase_note'] ) );
		}

		// Featured Product
		if ( isset( $data['featured'] ) ) {
			$product->set_featured( $data['featured'] );
		}

		// Shipping data
		//$product = $this->save_product_shipping_data( $product, $data );

		// SKU
		if ( isset( $data['sku'] ) ) {
			$sku     = $product->get_sku();
			$new_sku = wc_clean( $data['sku'] );

			if ( '' == $new_sku ) {
				$product->set_sku( '' );
			} elseif ( $new_sku !== $sku ) {
				if ( ! empty( $new_sku ) ) {
					$unique_sku = wc_product_has_unique_sku( $product->get_id(), $new_sku );
					if ( ! $unique_sku ) {
						throw new \WC_API_Exception( 'woocommerce_api_product_sku_already_exists', __( 'The SKU already exists on another product.', 'woocommerce' ), 400 );
					} else {
						$product->set_sku( $new_sku );
					}
				} else {
					$product->set_sku( '' );
				}
			}
		}


		// Sales and prices.
		if ( in_array( $product->get_type(), array( 'variable', 'grouped' ) ) ) {
			// Variable and grouped products have no prices.
			$product->set_regular_price( '' );
			$product->set_sale_price( '' );
			$product->set_date_on_sale_to( '' );
			$product->set_date_on_sale_from( '' );
			$product->set_price( '' );
		} else {
			// Regular Price.
			if ( isset( $data['regular_price'] ) ) {
				$regular_price = ( '' === $data['regular_price'] ) ? '' : $data['regular_price'];
				$product->set_regular_price( $regular_price );
			}

			// Sale Price.
			if ( isset( $data['sale_price'] ) ) {
				$sale_price = ( '' === $data['sale_price'] ) ? '' : $data['sale_price'];
				$product->set_sale_price( $sale_price );
			}

			if ( isset( $data['sale_price_dates_from'] ) ) {
				$date_from = $data['sale_price_dates_from'];
			} else {
				$date_from = $product->get_date_on_sale_from() ? date( 'Y-m-d', $product->get_date_on_sale_from()->getTimestamp() ) : '';
			}

			if ( isset( $data['sale_price_dates_to'] ) ) {
				$date_to = $data['sale_price_dates_to'];
			} else {
				$date_to = $product->get_date_on_sale_to() ? date( 'Y-m-d', $product->get_date_on_sale_to()->getTimestamp() ) : '';
			}

			if ( $date_to && ! $date_from ) {
				$date_from = strtotime( 'NOW', current_time( 'timestamp', true ) );
			}

			$product->set_date_on_sale_to( $date_to );
			$product->set_date_on_sale_from( $date_from );

			if ( $product->is_on_sale( 'edit' ) ) {
				$product->set_price( $product->get_sale_price( 'edit' ) );
			} else {
				$product->set_price( $product->get_regular_price( 'edit' ) );
			}
		}

		// Product parent ID for groups
		if ( isset( $data['parent_id'] ) ) {
			$product->set_parent_id( absint( $data['parent_id'] ) );
		}

		// Sold Individually
		if ( isset( $data['sold_individually'] ) ) {
			$product->set_sold_individually( true === $data['sold_individually'] ? 'yes' : '' );
		}

		// Stock status
		if ( isset( $data['in_stock'] ) ) {
			$stock_status = ( true === $data['in_stock'] ) ? 'instock' : 'outofstock';
		} else {
			$stock_status = $product->get_stock_status();

			if ( '' === $stock_status ) {
				$stock_status = 'instock';
			}
		}

		// Stock Data
		if ( 'yes' == get_option( 'woocommerce_manage_stock' ) ) {
			// Manage stock
			if ( isset( $data['managing_stock'] ) ) {
				$managing_stock = ( true === $data['managing_stock'] ) ? 'yes' : 'no';
				$product->set_manage_stock( $managing_stock );
			} else {
				$managing_stock = $product->get_manage_stock() ? 'yes' : 'no';
			}

			// Backorders
			if ( isset( $data['backorders'] ) ) {
				if ( 'notify' == $data['backorders'] ) {
					$backorders = 'notify';
				} else {
					$backorders = ( true === $data['backorders'] ) ? 'yes' : 'no';
				}

				$product->set_backorders( $backorders );
			} else {
				$backorders = $product->get_backorders();
			}

			if ( $product->is_type( 'grouped' ) ) {
				$product->set_manage_stock( 'no' );
				$product->set_backorders( 'no' );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( $stock_status );
			} elseif ( $product->is_type( 'external' ) ) {
				$product->set_manage_stock( 'no' );
				$product->set_backorders( 'no' );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( 'instock' );
			} elseif ( 'yes' == $managing_stock ) {
				$product->set_backorders( $backorders );

				// Stock status is always determined by children so sync later.
				if ( ! $product->is_type( 'variable' ) ) {
					$product->set_stock_status( $stock_status );
				}

				// Stock quantity
				if ( isset( $data['stock_quantity'] ) ) {
					$product->set_stock_quantity( wc_stock_amount( $data['stock_quantity'] ) );
				}
			} else {
				// Don't manage stock.
				$product->set_manage_stock( 'no' );
				$product->set_backorders( $backorders );
				$product->set_stock_quantity( '' );
				$product->set_stock_status( $stock_status );
			}
		} elseif ( ! $product->is_type( 'variable' ) ) {
			$product->set_stock_status( $stock_status );
		}

		// Upsells
		if ( isset( $data['upsell_ids'] ) ) {
			$upsells = array();
			$ids     = $data['upsell_ids'];

			if ( ! empty( $ids ) ) {
				foreach ( $ids as $id ) {
					if ( $id && $id > 0 ) {
						$upsells[] = $id;
					}
				}

				$product->set_upsell_ids( $upsells );
			} else {
				$product->set_upsell_ids( array() );
			}
		}

		// Cross sells
		if ( isset( $data['cross_sell_ids'] ) ) {
			$crosssells = array();
			$ids        = $data['cross_sell_ids'];

			if ( ! empty( $ids ) ) {
				foreach ( $ids as $id ) {
					if ( $id && $id > 0 ) {
						$crosssells[] = $id;
					}
				}

				$product->set_cross_sell_ids( $crosssells );
			} else {
				$product->set_cross_sell_ids( array() );
			}
		}

		// Product categories
		if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
			$product->set_category_ids( $data['categories'] );
		}

		// Product tags
		if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
			$product->set_tag_ids( $data['tags'] );
		}

		// Downloadable
		if ( isset( $data['downloadable'] ) ) {
			$is_downloadable = ( true === $data['downloadable'] ) ? 'yes' : 'no';
			$product->set_downloadable( $is_downloadable );
		} else {
			$is_downloadable = $product->get_downloadable() ? 'yes' : 'no';
		}

		// Downloadable options
		if ( 'yes' == $is_downloadable ) {
			// Download limit
			if ( isset( $data['download_limit'] ) ) {
				$product->set_download_limit( $data['download_limit'] );
			}

			// Download expiry
			if ( isset( $data['download_expiry'] ) ) {
				$product->set_download_expiry( $data['download_expiry'] );
			}
		}

		// Product url
		if ( $product->is_type( 'external' ) ) {
			if ( isset( $data['product_url'] ) ) {
				$product->set_product_url( $data['product_url'] );
			}

			if ( isset( $data['button_text'] ) ) {
				$product->set_button_text( $data['button_text'] );
			}
		}

		// Reviews allowed
		if ( isset( $data['reviews_allowed'] ) ) {
			$product->set_reviews_allowed( $data['reviews_allowed'] );
		}

		return $product->save();
	}


}