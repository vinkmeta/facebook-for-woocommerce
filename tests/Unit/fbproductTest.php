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
     * Test Data Provider for product category attributes
     */
    public function provide_category_data()
    {
        return [
            // Only FB attributes
            [
                173,
                array(
                ),
                array(
                    "size" => "medium",
                    "gender" => "female"
                ),
                array(
                    "size" => "medium",
                    "gender" => "female"
                ),
            ],
            // Only Woo attributes
            [
                173,
                array(
                    "size" => "medium",
                    "gender" => "female"
                ),
                array(
                ),
                array(
                    "size" => "medium",
                    "gender" => "female"
                ),
            ],
            // Both Woo and FB attributes
            [
                173,
                array(
                    "color" => "black",
                    "material" => "cotton"
                ),
                array(
                    "size" => "medium",
                    "gender" => "female"
                ),
                array(
                    "color" => "black",
                    "material" => "cotton",
                    "size" => "medium",
                    "gender" => "female"
                ),
            ],
            // Woo attributes with space, '-' and different casing
            [
                173,
                array(
                    "age group" => "teen",
                    "is-costume" => "yes",
                    "Sunglasses Width" => "narrow"
                ),
                array(
                ),
                array(
                    "age_group" => "teen",
                    "is_costume" => "yes",
                    "sunglasses_width" => "narrow"
                ),
            ],
            // FB attributes overriding Woo attributes
            [
                173,
                array(
                    "age_group" => "teen",
                    "size" => "medium",
                ),
                array(
                    "age_group" => "toddler",
                    "size" => "large",
                ),
                array(
                    "age_group" => "toddler",
                    "size" => "large",
                ),
            ],
        ];
    }

    /**
     * Test that attribute related fields are being set correctly while preparing product.
     *
     * @dataProvider provide_category_data
     * @return void
     */
    public function test_enhanced_catalog_fields_from_attributes(
        $category_id,
        $woo_attributes,
        $fb_attributes,
        $expected_attributes
    ) {
        $product          = WC_Helper_Product::create_simple_product();
        $product->update_meta_data('_wc_facebook_google_product_category', $category_id);

        // Set Woo attributes
        $attributes = array();
        $position = 0;
        foreach ($woo_attributes as $key => $value) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(0);
            $attribute->set_name($key);
            $attribute->set_options(array($value));
            $attribute->set_position($position++);
            $attribute->set_visible(1);
            $attribute->set_variation(0);
            $attributes[] = $attribute;
        }
        $product->set_attributes($attributes);

        // Set FB sttributes
        foreach ($fb_attributes as $key => $value) {
            $product->update_meta_data('_wc_facebook_enhanced_catalog_attributes_'.$key, $value);
        }
        $product->save_meta_data();

        // Prepare Product and validate assertions
        $facebook_product = new \WC_Facebook_Product($product);
        $product_data = $facebook_product->prepare_product(
            $facebook_product->get_id(),
            \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH
        );
        $this->assertEquals($product_data['google_product_category'], $category_id);
        foreach ($expected_attributes as $key => $value) {
            $this->assertEquals($product_data[$key], $value);
        }

        $product_data = $facebook_product->prepare_product(
            $facebook_product->get_id(),
            \WC_Facebook_Product::PRODUCT_PREP_TYPE_FEED
        );
        $this->assertEquals($product_data['category'], 173);
        foreach ($expected_attributes as $key => $value) {
            $this->assertEquals($product_data[$key], $value);
        }
    }
}
