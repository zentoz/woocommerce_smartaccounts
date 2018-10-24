<?php
/**
 * Created by Margus Pala.
 * Date: 17.09.18
 * Time: 18:57
 */

include_once 'wp-async-request.php';
include_once 'wp-background-process.php';

class SmartAccountsArticleAsync extends WP_Background_Process
{

    public static function syncSaProducts()
    {
        error_log('Starting to sync products from SmartAccounts');
        $api             = new SmartAccountsApi();
        $page            = 1;
        $productsBatches = [];
        $products        = [];
        do {
            $result = $api->sendRequest(null, "purchasesales/articles:get", "pageNumber=$page");
            if (isset($result['articles']) && is_array($result['articles'])) {
                foreach ($result['articles'] as $article) {
                    if ($article['activeSales'] && ($article['type'] == 'PRODUCT' || $article['type'] == 'WH')) {
                        if (count($products) == 10) {
                            $productsBatches[] = $products;
                            $products          = [];
                        }
                        $products[$article['code']] = [
                            'code'        => $article['code'],
                            'price'       => $article['priceSales'],
                            'description' => $article['description'],
                            'quantity'    => 0
                        ];
                        error_log('SA sync code ' . $article['code']);
                    }
                }
            }
            $page++;
        } while ($result->hasMoreEntries);

        if (count($products) > 0) {
            $productsBatches[] = $products;
        }

        $background = new SmartAccountsArticleAsync();
        foreach ($productsBatches as $batch) {
            $background->push_to_queue($batch);
        }

        $background->save()->dispatch();

        wp_send_json(['message' => "Sync initiated"]);
    }

    protected $action = 'smartaccounts_product_import';

    function task($products)
    {
        $api          = new SmartAccountsApi();
        $productCodes = [];
        foreach ($products as $product) {
            $productCodes[] = urlencode($product['code']);
        }

        $result = $api->sendRequest(null, "purchasesales/articles:getwarehousequantities",
            "codes=" . implode(",", $productCodes));
        sleep(2); // needed to not exceed API request limits
        if (isset($result['quantities']) && is_array($result['quantities'])) {
            error_log('Received product quantities ' . json_encode($result['quantities']));
            foreach ($result['quantities'] as $quantity) {
                $products[$quantity['code']]['quantity'] = intval($quantity['quantity']);
            }
            error_log("Quantity set: " . json_encode($products));
        }
        foreach ($products as $code => $product) {
            $productId = wc_get_product_id_by_sku($code);
            if ( ! $productId) {
                error_log("Inserting product $code");
                $this->insertProduct($code, $product);
            } else {
                error_log("Updating product: $code - $productId");
                $existingProduct = wc_get_product($productId);
                $this->insertProduct($code, $product, $existingProduct);
            }
        }

        return false;
    }

    protected function insertProduct($code, $data, $existing = null)
    {
        if ($existing == null) {
            $post_id = wp_insert_post([
                'post_title'   => $data['description'],
                'post_content' => $data['description'],
                'post_status'  => 'publish',
                'post_type'    => "product",
            ], true);
            if (is_wp_error($post_id)) {
                error_log("Insert failed: " . $post_id->get_error_message());

                return;
            } else {
                error_log("New product ID $post_id");
            }
        } else {
            $post_id = $existing->get_id();
        }

        $settings = json_decode(get_option("sa_settings"));
        if ($settings && $settings->backorders) {
            $backorders = 'yes';
        } else {
            $backorders = 'no';
        }

        update_post_meta($post_id, '_sku', $code);
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, '_regular_price', $data['price']);
        update_post_meta($post_id, '_price', $data['price']);
        update_post_meta($post_id, '_featured', 'no');
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_backorders', $backorders);
        update_post_meta($post_id, '_stock_status', intval($data['quantity']) < 1 ? 'outofstock' : 'instock');
        wc_update_product_stock($post_id, $data['quantity']);

        error_log("Update stock for $post_id - $code to " . $data['quantity']);
    }
}