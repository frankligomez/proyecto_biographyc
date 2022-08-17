<?php
/**
 * Author: Rob
 * Date: 6/24/13
 * Time: 11:31 AM
 */

$p_dir;
$s_order;

class FilemanagerModel {
    ////////////////////////////////////////////////////////////////////////////////////////
    // Events                                                                             //
    ////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////
    // Constants                                                                          //
    ////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////
    // Variables                                                                          //
    ////////////////////////////////////////////////////////////////////////////////////////
    private $controller;


    ////////////////////////////////////////////////////////////////////////////////////////
    // Constructor & Destructor                                                           //
    ////////////////////////////////////////////////////////////////////////////////////////
    public function __construct($controller) {
      $this->controller = $controller;
    }


    ////////////////////////////////////////////////////////////////////////////////////////
    // Public Methods                                                                     //
    ////////////////////////////////////////////////////////////////////////////////////////
    public function get_file_manager_data() {
      $session_data = array();
      $session_data['sort_by'] = $this->get_from_session('sort_by', 'name');
      $session_data['sort_order'] = $this->get_from_session('sort_order', 'asc');
      $session_data['items_view'] = $this->get_from_session('items_view', 'thumbs');
      $session_data['clipboard_task'] = $this->get_from_session('clipboard_task', '');
      $session_data['clipboard_files'] = $this->get_from_session('clipboard_files', '');
      $session_data['clipboard_src'] = $this->get_from_session('clipboard_src', '');
      $session_data['clipboard_dest'] = $this->get_from_session('clipboard_dest', '');

      $data = array();
      $data['session_data'] = $session_data;
      $data['path_components'] = $this->get_path_components();
      $data['dir'] = (isset($_REQUEST['dir']) ? esc_html($_REQUEST['dir']) : '');
      $data['files'] = $this->get_files($session_data['sort_by'], $session_data['sort_order']);
      $data['media_library_files'] = ($this->controller->get_options_data()->enable_ML_import ? $this->get_media_library_files($session_data['sort_by'], $session_data['sort_order']) : array());
      $data['extensions'] = (isset($_REQUEST['extensions']) ? esc_html($_REQUEST['extensions']) : '');
      $data['callback'] = (isset($_REQUEST['callback']) ? esc_html($_REQUEST['callback']) : '');

      return $data;
    }


    ////////////////////////////////////////////////////////////////////////////////////////
    // Getters & Setters                                                                  //
    ////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////
    // Private Methods                                                                    //
    ////////////////////////////////////////////////////////////////////////////////////////
    private function get_from_session($key, $default) {
      // if (isset($_SESSION[$key])) {
      // if (isset($_REQUEST[$key])) {
        if (isset($_REQUEST[$key])) {
          // $_SESSION[$key] = $_REQUEST[$key];
          $_REQUEST[$key] = stripslashes($_REQUEST[$key]);
        }
        else {
          // $_SESSION[$key] = $default;
          $_REQUEST[$key] = stripslashes($default);
        }
        // return $_SESSION[$key];
        return esc_html(stripslashes($_REQUEST[$key]));
      // }
      // return '';
    }

    public function get_path_components() {
      $dir_names = explode('/', (isset($_REQUEST['dir']) ? esc_html($_REQUEST['dir']) : ''));
      $path = '';

      $components = array();
      $component = array();
      global $WD_BWG_UPLOAD_DIR;
      $component['name'] = $WD_BWG_UPLOAD_DIR;
      $component['path'] = $path;
      $components[] = $component;
      for ($i = 0; $i < count($dir_names); $i++) {
        $dir_name = $dir_names[$i];
        if ($dir_name == '') {
            continue;
        }
        $path .= (($path == '') ? $dir_name : '/' . $dir_name);
        $component = array();
        $component['name'] = $dir_name;
        $component['path'] = $path;
        $components[] = $component;
      }
      return $components;
    }

    function get_files($sort_by, $sort_order) {
      $icons_dir_path = WD_BWG_DIR . '/filemanager/images/file_icons';
      $icons_dir_url = WD_BWG_URL . '/filemanager/images/file_icons';
      $valid_types = explode(',', isset($_REQUEST['extensions']) ? strtolower(esc_html($_REQUEST['extensions'])) : '*');
      $parent_dir = $this->controller->get_uploads_dir() . (isset($_REQUEST['dir']) ? '/' . esc_html($_REQUEST['dir']) : '');
      $parent_dir_url = $this->controller->get_uploads_url() . (isset($_REQUEST['dir']) ? '/' . esc_html($_REQUEST['dir']) : '');


      $file_names = $this->get_sorted_file_names($parent_dir, $sort_by, $sort_order);

      $dirs = array();
      $files = array();
      foreach ($file_names as $file_name) {
        if (($file_name == '.') || ($file_name == '..') || ($file_name == 'thumb') || ($file_name == '.original')) {
          continue;
        }
        if (is_dir($parent_dir . '/' . $file_name) == TRUE) {
          $file = array();
          $file['is_dir'] = TRUE;
          $file['name'] = $file_name;
          $file['filename'] = $file_name;
          $file['type'] = '';
          $file['thumb'] = $icons_dir_url . '/dir.png';
          $file['icon'] = $icons_dir_url . '/dir.png';
          $file['size'] = '';
          $file['date_modified'] = '';
          $file['resolution'] = '';
          $dirs[] = $file;
        }
        else {
          $file = array();
          $file['is_dir'] = FALSE;
          $file['name'] = $file_name;
          $file['filename'] = substr($file_name, 0, strrpos($file_name, '.'));
          $file_extension = explode('.', $file_name);
          $file['type'] = strtolower(end($file_extension));
          $icon = $icons_dir_url . '/' . $file['type'] . '.png';
          if (file_exists($icons_dir_path . '/' . $file['type'] . '.png') == FALSE) {
            $icon = $icons_dir_url . '/' . '_blank.png';
          }
          $file['thumb'] = $this->is_img($file['type']) ? $parent_dir_url . '/thumb/' . $file_name : $icon;
          $file['icon'] = $icon;
          if (($valid_types[0] != '*') && (in_array($file['type'], $valid_types) == FALSE)) {
            continue;
          }
          $file_size_kb = (int)(filesize($parent_dir . '/' . $file_name) / 1024);
          // $file_size_mb = (int)($file_size_kb / 1024);
          // $file['size'] = $file_size_kb < 1024 ? (string)$file_size_kb . 'KB' : (string)$file_size_mb . 'MB';
          $file['size'] = $file_size_kb . ' KB';
          $file['date_modified'] = date('d F Y, H:i', filemtime($parent_dir . '/' . $file_name));
          $image_info = getimagesize(htmlspecialchars_decode($parent_dir . '/' . $file_name, ENT_COMPAT | ENT_QUOTES));
          $file['resolution'] = $this->is_img($file['type']) ? $image_info[0]  . ' x ' . $image_info[1] . ' px' : '';
          $exif = $this->bwg_wp_read_image_metadata( $parent_dir . '/.original/' . $file_name );
          $file['credit'] = $exif['credit'];          
          $file['aperture'] = $exif['aperture'];
          $file['camera'] = $exif['camera'];
          $file['caption'] = $exif['caption'];
          $file['iso'] = $exif['iso'];
          $file['orientation'] = $exif['orientation'];
          $file['copyright'] = $exif['copyright'];
          $files[] = $file;
        }
      }

      $result = $sort_order == 'asc' ? array_merge($dirs, $files) : array_merge($files, $dirs);
      return $result;
    }
    
    function get_media_library_files($sort_by, $sort_order) {
      $icons_dir_path = WD_BWG_DIR . '/filemanager/images/file_icons';
      $icons_dir_url = WD_BWG_URL . '/filemanager/images/file_icons';
      $valid_types = explode(',', isset($_REQUEST['extensions']) ? strtolower(esc_html($_REQUEST['extensions'])) : '*');
      $upload_dir = wp_upload_dir();
      $parent_dir = $upload_dir['basedir'];
      $parent_dir_url = $upload_dir['baseurl'];

      $query_images_args = array(
          'post_type' => 'attachment', 'post_mime_type' =>'image', 'post_status' => 'inherit', 'posts_per_page' => -1,
      );

      $query_images = new WP_Query( $query_images_args );

      $files = array();
      $upload_dir = wp_upload_dir();

      foreach ($query_images->posts as $image) {
        $file_meta = wp_get_attachment_metadata($image->ID);
        if (isset($file_meta['file'])) {
          $file = array();
          $file['is_dir'] = FALSE;
          $file_name_array = explode('/', $file_meta['file']);
          $file_name = end($file_name_array);
          $file['name'] = $file_name;
          $file['path'] = $file_meta['file'];
          $file['filename'] = substr($file_name, 0, strrpos($file_name, '.'));
          $file_type_array = explode('.', $file_name);
          $file['type'] = strtolower(end($file_type_array));
          // $file['thumb'] = wp_get_attachment_thumb_url($image->ID);
          if (!empty($file_meta['sizes']) && $file_meta['sizes']['thumbnail']['file']) {
            $file_pos = strrpos($file_meta['file'], '/');
            $sub_folder = substr($file_meta['file'], 0, $file_pos); 
            $file['thumb'] = $upload_dir['baseurl'] . '/' . $sub_folder . '/' . $file_meta['sizes']['thumbnail']['file'];
          }
          else {
            $file['thumb'] = $upload_dir['baseurl'] . '/' . $file_meta['file'];
          }
          $file['icon'] = $file['thumb'];
          if (($valid_types[0] != '*') && (in_array($file['type'], $valid_types) == FALSE)) {
            continue;
          }
          $file_size_kb = (int)(@filesize($parent_dir . '/' . $file_meta['file']) / 1024);
          if (!$file_size_kb) continue;
          $file['size'] = $file_size_kb . ' KB';
          $file['date_modified'] = date('d F Y, H:i', filemtime($parent_dir . '/' . $file_meta['file']));
          // $image_info = getimagesize(htmlspecialchars_decode($parent_dir . '/' . $file_meta['file'], ENT_COMPAT | ENT_QUOTES));
          $file['resolution'] = $this->is_img($file['type']) ? $file_meta['width']  . ' x ' . $file_meta['height'] . ' px' : '';
          $exif = $this->bwg_wp_read_image_metadata($parent_dir . '/.original/' . $file_name);          
          $file['credit'] = $exif['credit'];
          $file['aperture'] = $exif['aperture'];
          $file['camera'] = $exif['camera'];
          $file['caption'] = $exif['caption'];
          $file['iso'] = $exif['iso'];
          $file['orientation'] = $exif['orientation'];
          $file['copyright'] = $exif['copyright'];
          $files[] = $file;
        }
      }
      return $files;
    }

    private function bwg_wp_read_image_metadata( $file ) {
      if (!file_exists($file)) {
        return false;
      }
      list( , , $sourceImageType ) = getimagesize($file);
      $meta = array(
        'aperture' => 0,
        'credit' => '',
        'camera' => '',
        'caption' => '',
        'created_timestamp' => 0,
        'copyright' => '',
        'focal_length' => 0,
        'iso' => 0,
        'shutter_speed' => 0,
        'title' => '',
        'orientation' => 0,
      );	
      if ( is_callable( 'iptcparse' ) ) {
        getimagesize( $file, $info );
        if ( ! empty( $info['APP13'] ) ) {
          $iptc = iptcparse( $info['APP13'] );			
          if ( ! empty( $iptc['2#105'][0] ) ) {
            $meta['title'] = trim( $iptc['2#105'][0] );			
         