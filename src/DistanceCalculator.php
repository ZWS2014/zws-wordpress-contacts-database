<?php

namespace ZwsContactsDatabase;

/**
 * Distance calculator file for ZWS  Contacts Database
 *
 * @copyright Copyright (c) 2015, Zaziork Web Solutions
 * @author    Zaziork Web Solutions
 * @license This plugin uses the Composer library - see composer-license.txt
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @link https://www.zaziork.com/zws-wordpress-contacts-database-plugin/
 */
Class DistanceCalculator {

    // uses Google Distance Matrix API
    const DISTANCE_MATRIX_API_KEY = 'AIzaSyBWWGk2H9NwjOcF07YRXDXBimae3x7Dk9Y';
    const DISTANCE_MATRIX_BASE_URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';
    const DISTANCE_MATRIX_PARAMS = '&mode=driving&language=en-EN&units=imperial&sensor=false';

    public static function nearestContacts($how_many = 5, $target = NULL) {

        /* returns a multidimensional array of x contacts, sorted by distance from target, containing an innter array of details */

        if (isset($target)) {
            // set up variables
            $distance_array = array();
            
            // grab all registered users from db
            require_once(__DIR__ . '/Database.php');
            $result_set = \ZwsContactsDatabase\Database::getAllRecords();

            // loop postcodes and get distances
            if (isset($result_set) && $result_set !== false && is_array($result_set)) {
                foreach ($result_set as $key => $row) {
                    // get distance from contact to each target. Returns false if error or target not found.
                    $distance = \ZwsContactsDatabase\DistanceCalculator::getDistance(sanitize_text_field($target), sanitize_text_field($row->postcode));
                    // if distance successfully returned add to the distance array
                    if ($distance !== false) {
                        $distance_array[sanitize_text_field($row->id)] = array('distance' => $distance,
                            'postcode' => sanitize_text_field($row->postcode),
                            'lng' => sanitize_text_field($row->lng),
                            'lat' => sanitize_text_field($row->lat),
                            'first_name' => sanitize_text_field($row->first_name),
                            'last_name' => sanitize_text_field($row->last_name),
                            'phone' => sanitize_text_field($row->phone),
                            'email' => sanitize_email($row->email),
                            'max_radius' => sanitize_text_field($row->max_radius),
                            'extra_info' => esc_textarea($row->extra_info));
                    } 
                }
                if (!empty($distance_array)) {
                    // sort it
                    if (uasort($distance_array, array(__CLASS__, 'my_multidimensional_array_sorter'))) {
                        // only return the top $how_many (defaults to 5)
                        return array_slice($distance_array, 0, $how_many);
                    }
                }
            }
            return false;
        }
    }

    public static function getDistance($target_postcode, $contact_postcode) {
        require_once(__DIR__ . '/QueryAPI.php');

        $path = '?origins=' . $target_postcode . '&destinations=' . $contact_postcode . self::DISTANCE_MATRIX_PARAMS . '&key=' . self::DISTANCE_MATRIX_API_KEY;
        // $data = file_get_contents($url);
        $data = \ZwsContactsDatabase\QueryAPI::makeQuery(self::DISTANCE_MATRIX_BASE_URL, $path);
        if ($data['returned_data'] && $data['returned_data']['status'] === 'OK' && $data['returned_data']['rows'][0]['elements'][0]['status'] === "OK") {
            if ($data['cached']) {
                // error_log('THE DATA WAS CACHED ...'); // debug
            }
            return  round((sanitize_text_field($data['returned_data']['rows'][0]['elements'][0]['distance']['value']) * 0.000621371), 0, PHP_ROUND_HALF_UP);
        } else {
            error_log('An error occurred whilst attempting to get a distance, at \ZwsContactsDatabase\DistanceCalculator::getDistance');
            return false;
        }
    }

    private static function my_multidimensional_array_sorter($value1, $value2) {
        $sort_order = 'asc'; // default
        $sort_key = 'distance';
        if ($sort_order == 'asc') {
            return $value1[$sort_key] - $value2[$sort_key];
        } else {
            return $value2[$sort_key] - $value1[$sort_key];
        }
    }

}
