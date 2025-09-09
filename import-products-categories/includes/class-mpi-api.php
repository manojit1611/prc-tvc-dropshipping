<?php

if (!defined('ABSPATH')) exit;

class MPI_API
{
    public function mpi_get_auth_token()
    {
        $cached_token = get_transient('mpi_auth_token');
        if ($cached_token) {
            return $cached_token;
        }

        $auth_url = TVC_BASE_URL . "/Authorization/GetAuthorization?TVC_EMAIL=" . urlencode(TVC_EMAIL) . "&TVC_PASSWORD=" . urlencode(TVC_PASSWORD);

        $response = wp_remote_get($auth_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['AuthorizationToken'])) {
            set_transient('mpi_auth_token', $data['AuthorizationToken'], 3600); // cache for 1 hour
            return $data['AuthorizationToken'];
        }

        return false;
    }

    public function get_categories_from_api($parentCategoryCode = null)
    {
        $token = $this->mpi_get_auth_token();
        if (!$token) {
            return ['error' => 'Failed to retrieve authentication token'];
        }
        $api_url = TVC_BASE_URL . "/OpenApi/Category/GetChildren?ParentCode=" . urlencode($parentCategoryCode);
        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'TVC ' . $token,
            ],
            'timeout' => 30,
        ];

        $response = wp_remote_get($api_url, $args);
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function get_products_by_category_code($categoryCode, $lastProductId = null, $perPage = 50, $pageIndex = 1, $beginDate = null, $endDate = null)
    {
        $token = $this->mpi_get_auth_token();
        if (!$token) {
            return ['error' => 'Failed to retrieve authentication token'];
        }

        // Base API URL
        $api_url = TVC_BASE_URL . "/openapi/Product/Search?pageSize=" . intval($perPage)
                . "&pageIndex=" . intval($pageIndex)
                . "&CategoryCode=" . urlencode($categoryCode);

        // If lastProductId is provided, add it to query
        if (!empty($lastProductId)) {
            $api_url .= "&lastProductId=" . urlencode($lastProductId);
        }

        if (!empty($beginDate)) {
            $api_url .= "&beginDate=" . urlencode($beginDate);
        }

        if (!empty($endDate)) {
            $api_url .= "&endDate=" . urlencode($endDate);
        }

        $args = [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'TVC ' . $token,
            ],
            'timeout' => 30,
        ];

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data;
    }

}