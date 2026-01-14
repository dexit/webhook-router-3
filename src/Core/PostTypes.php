<?php

namespace ProgradeOort\Core;

class PostTypes
{
    private static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', [$this, 'register_endpoint_cpt']);
    }

    public function register_endpoint_cpt()
    {
        $labels = [
            'name'               => _x('Oort Endpoints', 'Post Type General Name', 'prograde-oort'),
            'singular_name'      => _x('Oort Endpoint', 'Post Type Singular Name', 'prograde-oort'),
            'menu_name'          => __('Oort Endpoints', 'prograde-oort'),
            'name_admin_bar'     => __('Oort Endpoint', 'prograde-oort'),
            'add_new'            => __('Add New', 'prograde-oort'),
            'add_new_item'       => __('Add New Endpoint', 'prograde-oort'),
            'new_item'           => __('New Endpoint', 'prograde-oort'),
            'edit_item'          => __('Edit Endpoint', 'prograde-oort'),
            'view_item'          => __('View Endpoint', 'prograde-oort'),
            'all_items'          => __('All Endpoints', 'prograde-oort'),
            'search_items'       => __('Search Endpoints', 'prograde-oort'),
            'not_found'          => __('No endpoints found.', 'prograde-oort'),
            'not_found_in_trash' => __('No endpoints found in Trash.', 'prograde-oort'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'prograde-oort',
            'query_var'          => true,
            'rewrite'            => ['slug' => 'oort_endpoint'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => ['title', 'revisions', 'custom-fields'],
            'show_in_rest'       => false, // We handle our own REST registration
        ];

        register_post_type('oort_endpoint', $args);

        // Custom columns in list table
        add_filter('manage_oort_endpoint_posts_columns', [$this, 'set_oort_endpoint_columns']);
        add_action('manage_oort_endpoint_posts_custom_column', [$this, 'render_oort_endpoint_columns'], 10, 2);
        add_filter('manage_edit-oort_endpoint_sortable_columns', [$this, 'set_sortable_oort_endpoint_columns']);
    }

    public function set_oort_endpoint_columns($columns)
    {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['oort_type'] = __('Type', 'prograde-oort');
                $new_columns['oort_path'] = __('Path', 'prograde-oort');
                $new_columns['oort_trigger'] = __('Trigger', 'prograde-oort');
            }
        }
        return $new_columns;
    }

    public function render_oort_endpoint_columns($column, $post_id)
    {
        switch ($column) {
            case 'oort_type':
                $type = get_post_meta($post_id, '_oort_route_type', true);
                echo esc_html(strtoupper((string)($type ?: 'rest')));
                break;
            case 'oort_path':
                $path = get_post_meta($post_id, '_oort_route_path', true);
                echo '<code>/' . esc_html(ltrim((string)$path, '/')) . '</code>';
                break;
            case 'oort_trigger':
                $trigger = get_post_meta($post_id, '_oort_trigger', true);
                $choices = [
                    'webhook' => __('Incoming Webhook', 'prograde-oort'),
                    'ingestion' => __('Feed Ingestion', 'prograde-oort'),
                    'event' => __('Internal Event', 'prograde-oort'),
                    'manual' => __('Manual Call', 'prograde-oort'),
                ];
                echo esc_html($choices[$trigger] ?? ($trigger ?: 'webhook'));
                break;
        }
    }

    public function set_sortable_oort_endpoint_columns($columns)
    {
        $columns['oort_type'] = 'oort_type';
        $columns['oort_path'] = 'oort_path';
        return $columns;
    }
}
