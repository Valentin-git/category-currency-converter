<?php
/*
Plugin Name: Category Currency Converter
Description: Конвертирует валюту для определенной категории товаров в WooCommerce с использованием курса валют от Национального банка Украины
Version: 1.3
Author: Valentun
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Загрузка файла с функциями обновления курса валют
require_once plugin_dir_path( __FILE__ ) . 'updater.php';

register_activation_hook( __FILE__, 'category_currency_converter_activate' );
register_deactivation_hook( __FILE__, 'category_currency_converter_deactivate' );

function category_currency_converter( $price, $product ) {
   $exchange_rate = get_option( 'category_currency_converter_exchange_rate', 1 );

   $selected_categories = get_option( 'category_currency_converter_selected_categories', array() );

   if ( $product->is_type( 'variation' ) ) {
       $product_id = $product->get_parent_id();
   } else {
       $product_id = $product->get_id();
   }

   $product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
   if ( is_wp_error( $product_categories ) ) {
       return $price;
   }

   $should_convert = false;
   foreach ( $product_categories as $category_id ) {
       if ( in_array( absint( $category_id ), $selected_categories, true ) ) {
           $should_convert = true;
           break;
       }
   }

   if ( $should_convert && is_numeric( $price ) ) {
       $price = round(floatval($price) * floatval($exchange_rate), 2);
   }

   return $price;
}

add_filter( 'woocommerce_product_get_price', 'category_currency_converter', 100, 2 );
add_filter( 'woocommerce_product_variation_get_price', 'category_currency_converter', 100, 2 );
add_filter( 'woocommerce_product_get_regular_price', 'category_currency_converter', 100, 2 );
add_filter( 'woocommerce_product_variation_get_regular_price', 'category_currency_converter', 100, 2 );
add_filter( 'woocommerce_product_get_sale_price', 'category_currency_converter', 100, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'category_currency_converter', 100, 2 );

add_filter( 'woocommerce_get_price_html', 'category_currency_converter_price_html', 100, 2 );

function category_currency_converter_price_html( $price_html, $product ) {
   if ( $product->is_type( 'variable' ) ) {
       $min_regular_price = $product->get_variation_regular_price( 'min', true );
       $max_regular_price = $product->get_variation_regular_price( 'max', true );
       $min_sale_price    = $product->get_variation_sale_price( 'min', true );
       $max_sale_price    = $product->get_variation_sale_price( 'max', true );

       $min_regular_price = category_currency_converter( $min_regular_price, $product );
       $max_regular_price = category_currency_converter( $max_regular_price, $product );
       $min_sale_price    = category_currency_converter( $min_sale_price, $product );
       $max_sale_price    = category_currency_converter( $max_sale_price, $product );

       if ( $min_sale_price !== $min_regular_price || $max_sale_price !== $max_regular_price ) {
           $price_html = wc_format_price_range( $min_sale_price, $max_sale_price );
       } else {
           $price_html = wc_format_price_range( $min_regular_price, $max_regular_price );
       }
   }

   return $price_html;
}



// Создание страницы настроек плагина
function category_currency_converter_settings() {
    add_options_page(
        'Category Currency Converter',
        'Category Currency Converter',
        'manage_options',
        'category-currency-converter',
        'category_currency_converter_settings_page'
    );
}
add_action( 'admin_menu', 'category_currency_converter_settings' );

// Отображение страницы настроек плагина
function category_currency_converter_settings_page() {
    ?>
    <div class="wrap">
        <h1>Category Currency Converter</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'category_currency_converter_settings_group' );
            do_settings_sections( 'category_currency_converter_settings_group' );
            $selected_categories = get_option( 'category_currency_converter_selected_categories', array() );
            $categories = get_terms( 'product_cat', array( 'hide_empty' => false ) );
            ?>
            <h2><?php _e( 'Select Categories', 'category-currency-converter' ); ?></h2>
            <ul>
               <?php if ( ! is_wp_error( $categories ) ) : ?>
               <?php foreach ( $categories as $category ) : ?>
               <li>
               <input type="checkbox" name="category_currency_converter_selected_categories[]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( absint( $category->term_id ), $selected_categories, true ) ); ?>>
               <?php echo esc_html( $category->name ); ?>
               </li>
               <?php endforeach; ?>
               <?php endif; ?>
            </ul>
            <h2><?php _e( 'Select Currency', 'category-currency-converter' ); ?></h2>
            <?php
            $selected_currency = get_option( 'category_currency_converter_currency', 'USD' );
            $currencies = array( 'USD', 'EUR', 'GBP' );
            ?>
            <select name="category_currency_converter_currency">
               <?php foreach ( $currencies as $currency ) : ?>
                     <option value="<?php echo esc_attr( $currency ); ?>" <?php selected( $selected_currency, $currency ); ?>><?php echo esc_html( $currency ); ?></option>
               <?php endforeach; ?>
            </select>

            <?php submit_button(); ?>
         </form>
         </div>
   <?php
   }
   // Регистрация настроек плагина
   function category_currency_converter_register_settings() {
      register_setting(
         'category_currency_converter_settings_group',
         'category_currency_converter_selected_categories',
      array(
         'type' => 'array',
         'sanitize_callback' => 'category_currency_converter_sanitize_selected_categories'
      )
      );

      register_setting(
         'category_currency_converter_settings_group',
         'category_currency_converter_currency',
      array(
         'type' => 'string',
         'sanitize_callback' => 'category_currency_converter_sanitize_currency'
      )
      );
   }
   add_action( 'admin_init', 'category_currency_converter_register_settings' );

   // Функция для очистки данных выбранных категорий
   function category_currency_converter_sanitize_selected_categories( $input ) {
      $output = array();
      if ( is_array( $input ) ) {
         foreach ( $input as $category_id ) {
            $output[] = absint( $category_id );
         }
      }
      return $output;
   }

   function category_currency_converter_sanitize_currency( $currency ) {
      $allowed_currencies = array( 'USD', 'EUR', 'GBP' );
      $currency = strtoupper( sanitize_text_field( $currency ) );

      if ( in_array( $currency, $allowed_currencies, true ) ) {
         return $currency;
      }

      return 'USD';
   }
