<?php

namespace DoubleThreeDigital\SimpleCommerce\Tests\Http\Controllers;

use DoubleThreeDigital\SimpleCommerce\Exceptions\CustomerNotFound;
use DoubleThreeDigital\SimpleCommerce\Facades\Customer;
use DoubleThreeDigital\SimpleCommerce\Facades\Order;
use DoubleThreeDigital\SimpleCommerce\Facades\Product;
use DoubleThreeDigital\SimpleCommerce\Tests\RefreshContent;
use DoubleThreeDigital\SimpleCommerce\Tests\SetupCollections;
use DoubleThreeDigital\SimpleCommerce\Tests\TestCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Statamic\Facades\Stache;

class CartItemControllerTest extends TestCase
{
    use SetupCollections, RefreshContent;

    public function setUp(): void
    {
        parent::setUp();

        $this->setupCollections();
        $this->useBasicTaxEngine();
    }

    /** @test */
    public function can_store_item()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function can_store_item_and_request_json()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->postJson(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertJsonStructure([
            'status',
            'message',
            'cart',
        ]);

        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function can_store_item_with_extra_data()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'foo' => 'bar',
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));

        $this->assertArrayHasKey('foo', $cart->lineItems()->first()['metadata']);
    }

    /** @test */
    public function can_store_item_and_ensure_custom_form_request_is_used()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            '_request' => CartItemStoreFormRequest::class,
            'product'  => $product->id,
            'quantity' => 1,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHasErrors('smth');
    }

    /** @test */
    public function can_store_item_with_variant()
    {
        $product = Product::make()
            ->data([
                'title'            => 'Dog Food',
                'slug'             => 'dog-food',
                'product_variants' => [
                    'variants' => [
                        [
                            'name'   => 'Colours',
                            'values' => [
                                'Red',
                            ],
                        ],
                        [
                            'name'   => 'Sizes',
                            'values' => [
                                'Small',
                            ],
                        ],
                    ],
                    'options' => [
                        [
                            'key'     => 'Red_Small',
                            'variant' => 'Red Small',
                            'price'   => 1000,
                        ],
                    ],
                ],
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'variant'  => 'Red_Small',
            'quantity' => 1,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function can_store_item_with_metadata_where_metadata_is_unique()
    {
        Config::set('simple-commerce.cart.unique_metadata', true);

        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id' => 'smth',
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total' => 1000,
                        'metadata' => [
                            'foo' => 'bar',
                            'bar' => 'baz',
                        ],
                    ],
                ],
                'items_total' => 1000,
                'grand_total' => 1000,
            ]);

        $cart->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'foo' => 'bar',
            'barz' => 'baz',
        ];

        $response = $this
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(2000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));

        $this->assertSame(1, $cart->lineItems()->first()['quantity']);
        $this->assertArrayHasKey('foo', $cart->lineItems()->first()['metadata']);
        $this->assertArrayHasKey('bar', $cart->lineItems()->first()['metadata']);
        $this->assertArrayNotHasKey('barz', $cart->lineItems()->first()['metadata']);

        $this->assertSame(1, $cart->lineItems()->first()['quantity']);
        $this->assertArrayHasKey('foo', $cart->lineItems()->last()['metadata']);
        $this->assertArrayNotHasKey('bar', $cart->lineItems()->last()['metadata']);
        $this->assertArrayHasKey('barz', $cart->lineItems()->last()['metadata']);

        Config::set('simple-commerce.cart.unique_metadata', false);
    }

    /** @test */
    public function can_store_item_with_metadata_where_metadata_is_not_unique()
    {
        Config::set('simple-commerce.cart.unique_metadata', true);

        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id' => 'smth',
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total' => 1000,
                        'metadata' => [
                            'foo' => 'bar',
                            'bar' => 'baz',
                        ],
                    ],
                ],
                'items_total' => 1000,
                'grand_total' => 1000,
            ]);

        $cart->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'foo' => 'bar',
            'bar' => 'baz',
        ];

        $response = $this
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(2000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));

        $this->assertSame(2, $cart->lineItems()->first()['quantity']);

        $this->assertArrayHasKey('foo', $cart->lineItems()->first()['metadata']);
        $this->assertArrayHasKey('bar', $cart->lineItems()->first()['metadata']);

        Config::set('simple-commerce.cart.unique_metadata', false);
    }

    /** @test */
    public function can_store_item_with_existing_cart()
    {
        $product = Product::make()
            ->data([
                'title' => 'Cat Food',
                'slug' => 'cat-food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make();
        $cart->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = $cart->fresh();

        $this->assertSame(1000, $cart->get('items_total'));
        $this->assertSame(session()->get('simple-commerce-cart'), $cart->id);
        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function can_store_item_and_ensure_the_quantity_is_not_more_than_stock()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1567,
                'stock' => 2,
            ]);

        $product->save();

        $cart = Order::make();
        $cart->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 5,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.store'), $data)
            ->assertSessionHasErrors();
    }

    /** @test */
    public function can_store_item_with_variant_and_ensure_the_quantity_is_not_more_than_stock()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'product_variants' => [
                    'variants' => [
                        [
                            'name'   => 'Colours',
                            'values' => [
                                'Red',
                            ],
                        ],
                        [
                            'name'   => 'Sizes',
                            'values' => [
                                'Small',
                            ],
                        ],
                    ],
                    'options' => [
                        [
                            'key'     => 'Red_Small',
                            'variant' => 'Red Small',
                            'price'   => 1000,
                            'stock'   => 2,
                        ],
                    ],
                ],
            ]);

        $product->save();

        $cart = Order::make();
        $cart->save();

        $data = [
            'product'  => $product->id,
            'variant' => 'Red_Small',
            'quantity' => 5,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.store'), $data)
            ->assertSessionHasErrors();
    }

    /** @test */
    public function can_store_item_and_ensure_existing_items_are_not_overwritten()
    {
        $productOne = Product::make()
            ->data([
                'title' => 'Rabbit Food',
                'slug' => 'rabbit-food',
                'price' => 1000,
            ]);

        $productOne->save();

        $productTwo = Product::make()
            ->data([
                'title' => 'Fish Food',
                'slug' => 'fish-food',
                'price' => 2300,
            ]);

        $productTwo->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $productOne->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'product'  => $productTwo->id,
            'quantity' => 1,
        ];

        $response = $this
            ->from('/products/' . $productTwo->get('slug'))
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $productTwo->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = $cart->fresh();

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertSame(session()->get('simple-commerce-cart'), $cart->id);
        // $this->assertSame(3300, $cart->get('items_total'));

        $this->assertStringContainsString($productOne->id, json_encode($cart->get('items')));
        $this->assertStringContainsString($productTwo->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function can_store_item_with_custom_redirect_url()
    {
        $product = Product::make()
            ->data([
                'title' => 'Horse Food',
                'slug' => 'horse-food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            'product'   => $product->id,
            'quantity'  => 1,
            '_redirect' => '/checkout',
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/checkout');
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function can_store_item_with_name_and_email()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'name' => 'Michael Scott',
            'email' => 'michael@scott.net',
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));

        // Assert customer has been created with provided details
        $this->assertNotNull($cart->get('customer'));

        $this->assertSame($cart->customer()->name(), 'Michael Scott');
        $this->assertSame($cart->customer()->email(), 'michael@scott.net');
    }

    /** @test */
    public function cant_store_item_with_email_that_contains_spaces()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'name' => 'Spud Man',
            'email' => 'spud man@potato.net',
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data)
            ->assertSessionHasErrors()
            ->assertSessionMissing('simple-commerce-cart');

        try {
            Customer::findByEmail('spud man@potato.net');

            $this->assertTrue(false);
        } catch (CustomerNotFound $e) {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function can_store_item_with_only_email()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'email' => 'donald@duck.disney',
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));

        // Assert customer has been created with provided details
        $this->assertNotNull($cart->get('customer'));

        $this->assertSame($cart->customer()->name(), 'donald@duck.disney');
        $this->assertSame($cart->customer()->email(), 'donald@duck.disney');
    }

    /** @test */
    public function can_store_item_with_customer_already_in_present_in_order()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $customer = Customer::make()
            ->email('goofy@clubhouse.disney')
            ->data([
                'name' => 'Goofy',
            ]);

        $customer->save();

        $order = Order::make()
            ->data([
                'customer' => $customer->id,
            ]);

        $order->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
        ];

        $response = $this
            ->withSession(['simple-commerce-cart' => $order->id()])
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));

        // Assert customer has been created with provided details
        $this->assertNotNull($cart->get('customer'));

        $this->assertSame($cart->customer()->name(), 'Goofy');
        $this->assertSame($cart->customer()->email(), 'goofy@clubhouse.disney');
    }

    /** @test */
    public function can_store_item_with_customer_present_in_request()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $customer = Customer::make()
            ->email('pluto@clubhouse.disney')
            ->data([
                'name' => 'Pluto',
            ]);

        $customer->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'customer' => $customer->id,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id, json_encode($cart->get('items')));

        // Assert customer has been created with provided details
        $this->assertNotNull($cart->get('customer'));

        $this->assertSame($cart->customer()->name(), 'Pluto');
        $this->assertSame($cart->customer()->email(), 'pluto@clubhouse.disney');
    }

    /** @test */
    public function can_store_item_where_product_requires_prerequisite_product_and_customer_has_purchased_prerequisite_product()
    {
        $this->markTestSkipped();

        $prerequisiteProduct = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $prerequisiteProduct->save();

        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'price' => 1000,
                'prerequisite_product' => $prerequisiteProduct->id,
            ]);

        $product->save();

        $customer = Customer::make()
            ->email('test@test.test')
            ->data([
                'name' => 'Test Test',
            ]);

        $customer->save();

        Order::make()
            ->data([
                'items' => [
                    [
                        'id' => 'smth',
                        'product' => $prerequisiteProduct->id,
                        'quantity' => 1,
                        'total' => 1599,
                    ],
                ],
                'items_total' => 1599,
                'grand_total' => 1599,
                'customer' => $customer->id,
            ])
            ->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'customer' => $customer->id,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(1599, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($product->id(), json_encode($cart->get('items')));
    }

    /** @test */
    public function cant_store_item_where_product_requires_prerequisite_product_and_no_customer_available()
    {
        $prerequisiteProduct = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $prerequisiteProduct->save();

        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'price' => 1000,
                'prerequisite_product' => $prerequisiteProduct->id,
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $response->assertSessionHasErrors();

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertNotSame(2000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringNotContainsString($product->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function cant_store_item_where_product_requires_prerequisite_product_and_customer_has_not_purchased_prerequisite_product()
    {
        $prerequisiteProduct = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $prerequisiteProduct->save();

        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'price' => 1000,
                'prerequisite_product' => $prerequisiteProduct->id,
            ]);

        $product->save();

        $customer = Customer::make()
            ->email('test@test.test')
            ->data([
                'name' => 'Test Test',
            ]);

        $customer->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
            'customer' => $customer->id,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHas('simple-commerce-cart');

        $response->assertSessionHasErrors();

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertNotSame(2000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringNotContainsString($product->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function can_add_second_item_to_a_cart_with_an_existing_item()
    {
        $this->markTestSkipped();

        $productOne = Product::make()
            ->data([
                'title' => 'Product One',
                'price' => 1000,
            ]);

        $productOne->save();

        $productTwo = Product::make()
            ->data([
                'title' => 'Product Two',
                'price' => 1000,
            ]);

        $productTwo->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $productOne->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $cart->save();

        $this->assertCount(1, $cart->get('items'));

        $data = [
            'product'   => $productTwo->id,
            'quantity'  => 1,
            '_redirect' => '/checkout',
        ];

        $response = $this
            ->from('/products/' . $productTwo->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/checkout');
        $response->assertSessionHas('simple-commerce-cart');

        $cart = Order::find(session()->get('simple-commerce-cart'));

        $this->assertSame(2000, $cart->get('items_total'));

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertStringContainsString($productTwo->id, json_encode($cart->get('items')));
    }

    /** @test */
    public function can_store_a_product_that_is_already_in_the_cart()
    {
        $product = Product::make()
            ->data([
                'title' => 'Horse Food',
                'slug' => 'horse-food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'product'  => $product->id,
            'quantity' => 1,
        ];

        $response = $this
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.store'), $data)
            ->assertRedirect();

        $cart = $cart->fresh();

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertSame(1, count($cart->get('items')));
        $this->assertSame(2, $cart->get('items')[0]['quantity']);
    }

    /** @test */
    public function can_store_a_variant_that_is_already_in_the_cart()
    {
        $product = Product::make()
            ->data([
                'title'            => 'Dog Food',
                'slug' => 'dog-food',
                'product_variants' => [
                    'variants' => [
                        [
                            'name'   => 'Colours',
                            'values' => [
                                'Red',
                            ],
                        ],
                        [
                            'name'   => 'Sizes',
                            'values' => [
                                'Small',
                            ],
                        ],
                    ],
                    'options' => [
                        [
                            'key'     => 'Red_Small',
                            'variant' => 'Red Small',
                            'price'   => 1000,
                        ],
                    ],
                ],
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'      => Stache::generateId(),
                        'product' => $product->id,
                        'variant' => [
                            'variant' => 'Red_Small',
                            'product' => $product->id,
                        ],
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'product'  => $product->id,
            'variant'  => 'Red_Small',
            'quantity' => 4,
        ];

        $response = $this
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.store'), $data)
            ->assertRedirect();

        $cart = $cart->fresh();

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertSame(1, count($cart->get('items')));
        $this->assertSame(5, $cart->get('items')[0]['quantity']);
    }

    /** @test */
    public function can_store_variant_of_a_product_that_has_another_variant_that_is_in_the_cart()
    {
        $product = Product::make()
            ->data([
                'title'            => 'Dog Food',
                'slug' => 'dog-food',
                'product_variants' => [
                    'variants' => [
                        [
                            'name'   => 'Colours',
                            'values' => [
                                'Red',
                            ],
                        ],
                        [
                            'name'   => 'Sizes',
                            'values' => [
                                'Small',
                                'Medium',
                            ],
                        ],
                    ],
                    'options' => [
                        [
                            'key'     => 'Red_Small',
                            'variant' => 'Red Small',
                            'price'   => 1000,
                        ],
                        [
                            'key'     => 'Red_Medium',
                            'variant' => 'Red Medium',
                            'price'   => 1000,
                        ],
                    ],
                ],
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'      => Stache::generateId(),
                        'product' => $product->id,
                        'variant' => [
                            'variant' => 'Red_Small',
                            'product' => $product->id,
                        ],
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'product'  => $product->id,
            'variant'  => 'Red_Medium',
            'quantity' => 1,
        ];

        $response = $this
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.store'), $data)
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $cart = $cart->fresh();

        $this->assertArrayHasKey('items', $cart->data);
        $this->assertSame(2, count($cart->get('items')));
    }

    /** @test */
    public function cant_store_item_with_negative_quantity()
    {
        $product = Product::make()
            ->data([
                'title' => 'Dog Food',
                'slug' => 'dog-food',
                'price' => 1000,
            ]);

        $product->save();

        $data = [
            'product'  => $product->id,
            'quantity' => -1,
        ];

        $response = $this
            ->from('/products/' . $product->get('slug'))
            ->post(route('statamic.simple-commerce.cart-items.store'), $data);

        $response->assertRedirect('/products/' . $product->get('slug'));
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function can_update_item()
    {
        $product = Product::make()
            ->data([
                'title' => 'Food',
                'slug' => 'food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'quantity' => 2,
        ];

        $response = $this
            ->from('/cart')
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.update', [
                'item' => $cart->get('items')[0]['id'],
            ]), $data);

        $response->assertRedirect('/cart');

        $cart = $cart->fresh();

        $this->assertSame(2, $cart->get('items')[0]['quantity']);
    }

    /** @test */
    public function can_update_item_and_ensure_custom_form_request_is_used()
    {
        $product = Product::make()
            ->data([
                'title' => 'Food',
                'slug' => 'food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            '_request' => CartItemUpdateFormRequest::class,
            'quantity' => 2,
        ];

        $response = $this
            ->from('/cart')
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.update', [
                'item' => $cart->get('items')[0]['id'],
            ]), $data);

        $response->assertRedirect('/cart');
        $response->assertSessionHasErrors('coolzies');
    }

    /** @test */
    public function cant_update_item_with_zero_item_quantity()
    {
        $product = Product::make()
            ->data([
                'title' => 'Food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'quantity' => 0,
        ];

        $response = $this
            ->from('/cart')
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.update', [
                'item' => $cart->get('items')[0]['id'],
            ]), $data);

        $response->assertSessionHasErrors();

        $cart = $cart->fresh();

        $this->assertSame(1, $cart->get('items')[0]['quantity']);
    }

    /** @test */
    public function can_update_item_with_extra_data()
    {
        $product = Product::make()
            ->data([
                'title' => 'Food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'gift_note' => 'Have a good birthday!',
        ];

        $response = $this
            ->from('/cart')
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.update', [
                'item' => $cart->get('items')[0]['id'],
            ]), $data);

        $response->assertRedirect('/cart');

        $cart = $cart->fresh();

        $this->assertSame($cart->lineItems()->count(), 1);
        $this->assertArrayHasKey('metadata', $cart->lineItems()->first());
        $this->assertArrayNotHasKey('gift_note', $cart->lineItems()->first());
    }

    /** @test */
    public function can_update_item_with_extra_data_and_ensure_existing_metadata_isnt_overwritten()
    {
        $this->markTestSkipped();

        $product = Product::make()
            ->data([
                'title' => 'Food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                        'metadata' => [
                            'foo' => 'bar',
                        ],
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'bar' => 'baz',
        ];

        $response = $this
            ->from('/cart')
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.update', [
                'item' => $cart->get('items')[0]['id'],
            ]), $data);

        $response->assertRedirect('/cart');

        $cart = $cart->fresh();

        $this->assertSame($cart->lineItems()->count(), 1);
        $this->assertArrayHasKey('metadata', $cart->lineItems()->first());

        $this->assertArrayNotHasKey('foo', $cart->lineItems()->first());
        $this->assertArrayNotHasKey('bar', $cart->lineItems()->first());

        $this->assertSame($cart->get('items')[0]['metadata']['foo'], 'bar');
        $this->assertSame($cart->get('items')[0]['metadata']['bar'], 'baz');
    }

    /** @test */
    public function can_update_item_with_string_quantity_and_ensure_quantity_is_saved_as_integer()
    {
        $product = Product::make()
            ->data([
                'title' => 'Food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'quantity' => '3',
        ];

        $response = $this
            ->from('/cart')
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->post(route('statamic.simple-commerce.cart-items.update', [
                'item' => $cart->get('items')[0]['id'],
            ]), $data);

        $response->assertRedirect('/cart');

        $cart = $cart->fresh();

        $this->assertSame(3, $cart->get('items')[0]['quantity']);
        $this->assertIsInt($cart->get('items')[0]['quantity']);
    }

    /** @test */
    public function can_update_item_and_request_json()
    {
        $product = Product::make()
            ->data([
                'title' => 'Food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $data = [
            'quantity' => 2,
        ];

        $response = $this
            ->from('/cart')
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->postJson(route('statamic.simple-commerce.cart-items.update', [
                'item' => $cart->get('items')[0]['id'],
            ]), $data);

        $response->assertJsonStructure([
            'status',
            'message',
            'cart',
        ]);

        $cart = $cart->fresh();

        $this->assertSame(2, $cart->get('items')[0]['quantity']);
    }

    /** @test */
    public function can_destroy_item()
    {
        $this->markTestSkipped();

        $product = Product::make()
            ->data([
                'title' => 'Food',
                'price' => 1000,
            ]);

        $product->save();

        $cart = Order::make()
            ->data([
                'items' => [
                    [
                        'id'       => Stache::generateId(),
                        'product'  => $product->id,
                        'quantity' => 1,
                        'total'    => 1000,
                    ],
                ],
            ]);

        $cart->save();

        $response = $this
            ->from('/cart')
            ->withSession(['simple-commerce-cart' => $cart->id])
            ->deleteJson(route('statamic.simple-commerce.cart-items.destroy', [
                'item' => $cart->get('items')[0]['id'],
            ]));

        $response->assertJsonStructure([
            'status',
            'message',
            'cart',
        ]);

        $this->assertEmpty($cart->get('items'));
    }
}

class CartItemStoreFormRequest extends FormRequest
{
    public function rules()
    {
        return [
            'smth' => ['required', 'string'],
        ];
    }
}

class CartItemUpdateFormRequest extends FormRequest
{
    public function rules()
    {
        return [
            'coolzies' => ['required', 'string'],
        ];
    }
}
