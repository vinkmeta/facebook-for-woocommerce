<?php
declare(strict_types=1);


class fbproductTest extends WP_UnitTestCase {
	private $parent_fb_product;

	/**
	 * Test it gets description from post meta.
	 * @return void
	 */
	public function test_get_fb_description_from_post_meta() {
		$product = WC_Helper_Product::create_simple_product();

		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_description( 'fb description' );
		$description = $facebook_product->get_fb_description();

		$this->assertEquals( $description, 'fb description');
	}

	/**
	 * Test it gets description from parent product if it is a variation.
	 * @return void
	 */
	public function test_get_fb_description_variable_product() {
		$variable_product = WC_Helper_Product::create_variation_product();
		$variable_product->set_description('parent description');
		$variable_product->save();

		$parent_fb_product = new \WC_Facebook_Product($variable_product);
		$variation         = wc_get_product($variable_product->get_children()[0]);

		$facebook_product = new \WC_Facebook_Product( $variation, $parent_fb_product );
		$description      = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'parent description' );

		$variation->set_description( 'variation description' );
		$variation->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'variation description' );
	}

	/**
	 * Tests that if no description is found from meta or variation, it gets description from post
	 *
	 * @return void
	 */
	public function test_get_fb_description_from_post_content() {
		$product = WC_Helper_Product::create_simple_product();

		// Gets description from title
		$facebook_product = new \WC_Facebook_Product( $product );
		$description      = $facebook_product->get_fb_description();

		$this->assertEquals( $description, get_post( $product->get_id() )->post_title );

		// Gets description from excerpt (product short description)
		$product->set_short_description( 'short description' );
		$product->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_excerpt );

		// Gets description from content (product description)
		$product->set_description( 'product description' );
		$product->save();

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_content );

		// Gets description from excerpt ignoring content when short mode is set
		add_option(
			WC_Facebookcommerce_Integration::SETTING_PRODUCT_DESCRIPTION_MODE,
			WC_Facebookcommerce_Integration::PRODUCT_DESCRIPTION_MODE_SHORT
		);

		$facebook_product = new \WC_Facebook_Product( $product );
		$description      = $facebook_product->get_fb_description();
		$this->assertEquals( $description, get_post( $product->get_id() )->post_excerpt );
	}

	/**
	 * Test it filters description.
	 * @return void
	 */
	public function test_filter_fb_description() {
		$product = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_description( 'fb description' );

		add_filter( 'facebook_for_woocommerce_fb_product_description', function( $description ) {
			return 'filtered description';
		});

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'filtered description' );

		remove_all_filters( 'facebook_for_woocommerce_fb_product_description' );

		$description = $facebook_product->get_fb_description();
		$this->assertEquals( $description, 'fb description' );

	}

	/**
	 * Test Data Provider for sale_price related fields
	 */
	public function provide_sale_price_data() {
		return [
			[
				11.5,
				null,
				null,
				1150,
				'11.5 USD',
				'',
				'',
				'',
			],
			[
				0,
				null,
				null,
				0,
				'0 USD',
				'',
				'',
				'',
			],
			[
				null,
				null,
				null,
				0,
				'',
				'',
				'',
				'',
			],
			[
				null,
				'2024-08-08',
				'2024-08-18',
				0,
				'',
				'',
				'',
				'',
			],
			[
				11,
				'2024-08-08',
				null,
				1100,
				'11 USD',
				'2024-08-08T00:00:00+00:00/2038-01-17T23:59+00:00',
				'2024-08-08T00:00:00+00:00',
				'2038-01-17T23:59+00:00',
			],
			[
				11,
				null,
				'2024-08-08',
				1100,
				'11 USD',
				'1970-01-29T00:00+00:00/2024-08-08T00:00:00+00:00',
				'1970-01-29T00:00+00:00',
				'2024-08-08T00:00:00+00:00',
			],
			[
				11,
				'2024-08-08',
				'2024-08-09',
				1100,
				'11 USD',
				'2024-08-08T00:00:00+00:00/2024-08-09T00:00:00+00:00',
				'2024-08-08T00:00:00+00:00',
				'2024-08-09T00:00:00+00:00',
			],
		];
	}

	/**
	 * Test that sale_price related fields are being set correctly while preparing product.
	 *
	 * @dataProvider provide_sale_price_data
	 * @return void
	 */
	public function test_sale_price_and_effective_date(
		$salePrice,
		$sale_price_start_date,
		$sale_price_end_date,
		$expected_sale_price,
		$expected_sale_price_for_batch,
		$expected_sale_price_effective_date,
		$expected_sale_price_start_date,
		$expected_sale_price_end_date
	) {
		$product          = WC_Helper_Product::create_simple_product();
		$facebook_product = new \WC_Facebook_Product( $product );
		$facebook_product->set_sale_price( $salePrice );
		$facebook_product->set_date_on_sale_from( $sale_price_start_date );
		$facebook_product->set_date_on_sale_to( $sale_price_end_date );

		$product_data = $facebook_product->prepare_product( $facebook_product->get_id(), \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH );
		$this->assertEquals( $product_data['sale_price'], $expected_sale_price_for_batch );
		$this->assertEquals( $product_data['sale_price_effective_date'], $expected_sale_price_effective_date );

		$product_data = $facebook_product->prepare_product( $facebook_product->get_id(), \WC_Facebook_Product::PRODUCT_PREP_TYPE_FEED );
		$this->assertEquals( $product_data['sale_price'], $expected_sale_price );
		$this->assertEquals( $product_data['sale_price_start_date'], $expected_sale_price_start_date );
		$this->assertEquals( $product_data['sale_price_end_date'], $expected_sale_price_end_date );
	}
}
