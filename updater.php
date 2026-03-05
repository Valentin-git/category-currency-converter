<?php

if ( ! defined( 'ABSPATH' ) ) {
   exit; // Запрет прямого доступа
}
   
function schedule_exchange_rate_update() {
   if ( !wp_next_scheduled( 'category_currency_converter_update_exchange_rate' ) ) {
   wp_schedule_event( time(), 'every_24_hours', 'category_currency_converter_update_exchange_rate' );

   }
}
add_action( 'wp', 'schedule_exchange_rate_update' );

function update_exchange_rate() {
   $from_currency = get_option( 'category_currency_converter_currency', 'USD' );
   $to_currency = 'UAH';
   $api_url = "https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?valcode={$from_currency}&json";

   $response = wp_remote_get( $api_url );
   if ( !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
      $data = json_decode( wp_remote_retrieve_body( $response ), true );
   if ( isset( $data[0]['rate'] ) ) {
      $exchange_rate = $data[0]['rate']; // Получение курса обмена
      error_log( 'Fetched exchange rate: ' . $exchange_rate ); // Запись курса обмена в лог
      update_option( 'category_currency_converter_exchange_rate', $exchange_rate ); // Сохранение курса обмена
   }
   }
}
add_action( 'category_currency_converter_update_exchange_rate', 'update_exchange_rate' );

// обновять один раз в день
function custom_cron_schedules( $schedules ) {
   $schedules['every_24_hours'] = array(
      'interval' => 24 * 60 * 60, // 24 часа в секундах
      'display' => __( 'Every 24 hours' ),
   );
   return $schedules;
}
add_filter( 'cron_schedules', 'custom_cron_schedules' );