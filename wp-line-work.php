<?php
/**
 * Plugin Name: LINE MESSAGE WORK
 * Plugin URI:  https://wp-line.work/
 * Description: This plugin can send a line message by LINE 公式アカウント
 * Version:     1.0.0
 * Author:      anapple07
 * Author URI:  https://github.com/anapple07
 * License:     GPLv3 or later
 * Text Domain: wp-line-work
 * Domain Path: /languages
 */

define('SIG_LINE_WORK_PLUGIN_NAME', 'wp-line-work');
define('SIG_LINE_WORK_OPTIONS', '_sig_line_work_setting');
define('SIG_LINE_WORK_DIR', dirname(__FILE__));

require_once(dirname(__FILE__).'/LINEBot/Response.php');
require_once(dirname(__FILE__).'/LINEBot/HTTPClient/Curl.php');
require_once(dirname(__FILE__).'/LINEBot/Constant/MessageType.php');
require_once(dirname(__FILE__).'/LINEBot/Constant/ActionType.php');
require_once(dirname(__FILE__).'/LINEBot/Constant/TemplateType.php');
require_once(dirname(__FILE__).'/LINEBot/Constant/Meta.php');
require_once(dirname(__FILE__).'/LINEBot/HTTPClient.php');
require_once(dirname(__FILE__).'/LINEBot/HTTPClient/CurlHTTPClient.php');
require_once(dirname(__FILE__).'/LINEBot.php');
require_once(dirname(__FILE__).'/LINEBot/Util/BuildUtil.php');
require_once(dirname(__FILE__).'/LINEBot/MessageBuilder.php');
require_once(dirname(__FILE__).'/LINEBot/MessageBuilder/TextMessageBuilder.php');
require_once(dirname(__FILE__).'/LINEBot/MessageBuilder/TemplateMessageBuilder.php');
require_once(dirname(__FILE__).'/LINEBot/MessageBuilder/TemplateBuilder.php');
require_once(dirname(__FILE__).'/LINEBot/MessageBuilder/TemplateBuilder/CarouselColumnTemplateBuilder.php');
require_once(dirname(__FILE__).'/LINEBot/MessageBuilder/TemplateBuilder/CarouselTemplateBuilder.php');
require_once(dirname(__FILE__).'/LINEBot/TemplateActionBuilder.php');
require_once(dirname(__FILE__).'/LINEBot/TemplateActionBuilder/UriTemplateActionBuilder.php');

load_plugin_textdomain(SIG_LINE_WORK_PLUGIN_NAME, false, dirname(plugin_basename(__FILE__)) . '/languages/');

new LineBot();

class LineBot
{

    public function __construct()
    {
        $data = get_file_data(
            __FILE__,
            array('ver' => 'Version', 'langs' => 'Domain Path')
        );

        $this->options = get_option(SIG_LINE_WORK_OPTIONS);

        // add menu
        add_action('admin_menu', array($this,'add_option_menu'));
        add_filter("plugin_action_links_".plugin_basename(__FILE__), array($this, 'plugin_settings_link'));

        // send line
        add_action('transition_post_status', array($this, 'post_article'), 10, 3);

        // add api
        add_action('rest_api_init', function () {
            register_rest_route('wp-line-work/v1', 'post', array(
                'methods'  => 'POST',
                'callback' => array($this, 'get_webhook')
            ));
        });
    }

    public function get_webhook()
    {
        $json_string = file_get_contents('php://input');
        $headers = getallheaders();
        $headerSignature = $headers["X-Line-Signature"];
        $json_obj    = json_decode($json_string);
        $type        = $json_obj->events[0]->type;
        $user_id     = $json_obj->events[0]->source->userId;
        $replyToken  = $json_obj->events[0]->replyToken;

        $channel_access_token = $this->options['channel_access_token'];
        $channel_secret       = $this->options['channel_secret'];

        $hash = hash_hmac('sha256', $json_string, $channel_secret, true);
        $signature = base64_encode($hash);
        $httpRequestBody = $json_string;
        if ($headerSignature !== $signature) {
            return;
        }

        if ($type == "follow") {
            $new_posts_info = get_posts(
                array(
                    'posts_per_page'    => 10,
                    'post_status'       => 'publish',
                    'post_type'             => 'post',
                    'order'                 => 'DESC'
                )
            );

            $num = 0;
            $columns = [];
            foreach ($new_posts_info as &$post_info) {
                $post_detail = $this->get_post_detail($post_info);
                if (is_null($post_detail['thumbnail_url'])) {
                    continue;
                }
                $actions =  array ( new \LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder(
                    $post_detail['title'],
                    $post_detail['url']
                ) );
                $column = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder(
                    $post_detail['title'],
                    $post_detail['body'],
                    $post_detail['thumbnail_url'],
                    $actions
                );
                if ($num == 5) {
                    break;
                }
                $columns[] = $column;
                ++$num;
            }

            $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($channel_access_token);

            $bot        = new \LINE\LINEBot($httpClient, ['channelSecret' => $channel_secret]);
            $carousel         = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder($columns);
            $carousel_message = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder("Thank you for adding me :)", $carousel);
            $response   = $bot->replyMessage($replyToken, $carousel_message);
        }
        return;
    }

    private function get_post_detail($post_info)
    {

        $title  = substr($post_info->post_title, 0, 40);
        $body   = preg_replace("/( |　|\n|\r)/", "", strip_tags(sanitize_text_field($post_info->post_content)));
        $url    = get_permalink($post_info->ID);
        $truncated_body = mb_substr($body, 0, 40);
        if ($truncated_body != $body) {
            $truncated_body .= "…";
        }

        $thumbnail_url = get_the_post_thumbnail_url($post_info->ID, 'large');

        $post_detail =  array(
            "id"            => $post_info->ID,
            "body"          => $truncated_body,
            "thumbnail_url" => $thumbnail_url ? $thumbnail_url : null,
            "title"         => $title,
            "url"           => $url,
            "post_type"     => $post_info->post_type
        );

        return $post_detail;
    }


    public function post_article($new_status, $old_status, $post_info)
    {
        if (!(defined('REST_REQUEST') && REST_REQUEST )) {
            $post_detail = $this->get_post_detail($post_info);

            if ($post_detail['post_type'] != 'post') {
                return;
            }
            if ($old_status == 'publish') {
                return;
            }
            if ($new_status != 'publish') {
                return;
            }

            $channel_access_token = $this->options['channel_access_token'];
            $channel_secret       = $this->options['channel_secret'];

            $httpClient = new \LINE\LINEBot\HTTPClient\CurlHTTPClient($channel_access_token);

            $bot = new \LINE\LINEBot($httpClient, ['channelSecret' => $channel_secret]);
            $actions =  array ( new \LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder(
                $post_detail['title'],
                $post_detail['url']
            ) );
            $columns = array(  new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder(
                $post_detail['title'],
                $post_detail['body'],
                $post_detail['thumbnail_url'],
                $actions
            )
            );
            $carousel         = new \LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder($columns);
            $carousel_message = new \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder($post_detail['title'], $carousel);
            $response = $bot->broadcast($carousel_message);
        }
    }

    public function add_option_menu()
    {
        add_options_page(
            __('Line Offical Account Setting', SIG_LINE_WORK_PLUGIN_NAME),
            __('WP LINE Account', SIG_LINE_WORK_PLUGIN_NAME),
            'administrator',
            'sig-'.SIG_LINE_WORK_PLUGIN_NAME,
            array($this, 'html_settings_page')
        );

        add_action('admin_init', array($this,'register_option_var'));
    }

    public function plugin_settings_link($links)
    {
        $settings_link = '<a href="options-general.php?page=sig-'.SIG_LINE_WORK_PLUGIN_NAME.'">'.__('Settings', SIG_LINE_WORK_PLUGIN_NAME).'</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function register_option_var()
    {
        register_setting('line-work-option', SIG_LINE_WORK_OPTIONS);
    }

    public function html_settings_page()
    {

        require_once SIG_LINE_WORK_DIR . '/includes/page-setup.php';
    }
}
