<?php

class Service
{
    private static $instance;

    private $_fb;

    private $_db;

    private $_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() 
    {
        $this->_fb = new Facebook\Facebook([
            'app_id' => '1406945652966023', 
            'app_secret' => 'abebbe59b2920b313560606d966ac1ae', 
            'default_graph_version' => 'v2.5', 
        ]);

        $this->_db = new medoo([
            'database_type' => 'mysql',
            'database_name' => 'development_facebook_tools',
            'server' => 'localhost',
            'username' => 'root',
            'password' => '123456',
            'charset' => 'utf8'
        ]);
    }
    
    public static function getInstance() 
    {
        if (!self::$instance) 
        {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    public function setAccessToken($sAccessToken)
    {
        $this->_fb->setDefaultAccessToken($sAccessToken);
    }
    
    public function getAlbums($aParams) 
    {
        extract($aParams);

        if (empty($nodeId))
        {
            return array(
                'error' => array(
                    'message' => '(#4) Missing parameter: nodeId',
                    'type' => 'MissingParameter',
                    'code' => 4,
                )
            );
        }

        $res = $this->_getDecodedBody('/' . $nodeId . '/albums');

        return $res;
    }

    public function savePhoto($aParams)
    {
        extract($aParams);

        if (empty($id))
        {
            return array(
                'error' => array(
                    'message' => '(#4) Missing parameter: id',
                    'type' => 'MissingParameter',
                    'code' => 4,
                )
            );
        }

        $photo = $this->getPhoto($id);

        $from_id = $photo['from']['id'];
        $album_id = $photo['album']['id'];
        $image = $photo['images'][0];
        $image_name = $this->_getFileName($image['source']);
        
        $image_destination = BASE_DIR . 'file' . BASE_DS . 'photo' . BASE_DS . $from_id . BASE_DS . $album_id . BASE_DS;
        $image_path = $image_destination . $image_name;

        $res = array(
            'data' => array()
        );

        if (!$this->_fileExistPhoto($id))
        {
            $aParts = explode(BASE_DS, trim($image_destination, BASE_DS));
            $sParentDirectory = BASE_DS;
            foreach ($aParts as $sDir)
            {
                if (!is_dir($sParentDirectory . $sDir))
                {
                    mkdir($sParentDirectory . $sDir);
                    chmod($sParentDirectory . $sDir, 0777);
                }
                
                $sParentDirectory .= $sDir . BASE_DS;
            }

            $data = file_get_contents($image['source']);
            if ($data === false)
            {
                return array(
                    'error' => array(
                        'message' => '(#5) An error occurred in file_get_contents()',
                        'type' => 'FunctionError',
                        'code' => 5,
                    )
                );
            }

            $size = file_put_contents($image_path, $data);
            if ($size === false)
            {
                return array(
                    'error' => array(
                        'message' => '(#5) An error occurred in file_put_contents()',
                        'type' => 'FunctionError',
                        'code' => 5,
                    )
                );
            }
        }

        $res['data']['file_exist'] = true;

        if (!$this->_isSavedPhoto($id))
        {
            $insert_id = $this->_db->insert('facebook_photo', [
                'id' => $photo['id'],
                'album_id' => $album_id,
                'created_time' => $photo['created_time'],
                'from_id' => $from_id,
                'image_height' => $image['height'],
                'image_source' => $image['source'],
                'link' => $photo['link'],
                'name' => $photo['name'],
                'picture' => $photo['picture'],
                'updated_time' => $photo['updated_time'],
                'image_width' => $image['width'],
                'image_path' => str_replace(BASE_DIR . 'file', '', $image_path),
            ]);

            if (!$insert_id)
            {
                return array(
                    'error' => array(
                        'message' => '(#6) An error occurred in insert()',
                        'type' => 'DatabaseError',
                        'code' => 6,
                    )
                );
            }
        }

        $res['data']['is_saved'] = true;

        return $res;
    }

    public function getPhoto($id)
    {
        $endpoint = '/' . $id . '?fields=album,created_time,from,id,images,link,name,picture,source,updated_time';

        $res = $this->_getDecodedBody($endpoint);

        return $res;
    }

    public function getPhotos($aParams)
    {
        extract($aParams);

        if (empty($nodeId))
        {
            return array(
                'error' => array(
                    'message' => '(#4) Missing parameter: nodeId',
                    'type' => 'MissingParameter',
                    'code' => 4,
                )
            );
        }

        $limit = isset($limit) ? $limit : 25;
        $endpoint = '/' . $nodeId . '/photos?limit=' . $limit;

        if (isset($after))
        {
            $endpoint .= '&after=' . $after;
        }

        if (isset($before))
        {
            $endpoint .= '&before=' . $before;
        }

        $res = $this->_getDecodedBody($endpoint);

        if (!isset($res['error']))
        {
            $this->_processPhotoRows($res['data']);
        }

        return $res;
    }

    private function _processPhotoRows(&$rows)
    {
        foreach ($rows as $key => $row)
        {
            $rows[$key]['is_saved'] = $this->_isSavedPhoto($row['id']);
            $rows[$key]['file_exist'] = $this->_fileExistPhoto($row['id']);
        }
    }

    private function _fileExistPhoto($id)
    {
        if (!$this->_isSavedPhoto($id))
        {
            return false;
        }

        $row = $this->_getSavedPhoto($id);
        return file_exists(BASE_DIR . 'file' . $row['image_path']);
    }

    private function _isSavedPhoto($id)
    {
        if ($this->_getSavedPhoto($id))
        {
            return true;
        }

        return false;
    }

    private function _getSavedPhoto($id)
    {
        $rows = $this->_getSavedPhotos();

        foreach ($rows as $row)
        {
            if ($row['id'] == $id)
            {
                return $row;
            }
        }

        return null;
    }

    private function _getSavedPhotos()
    {
        if ($rows = $this->_getCache('facebook_photo'))
        {
            return $rows;
        }

        $rows = $this->_db->select('facebook_photo', '*');
        $this->_setCache('facebook_photo', $rows);

        return $rows;
    }

    private function _setCache($name, $value)
    {
        $this->_cache[$name] = $value;
    }

    private function _getCache($name)
    {
        return $this->_cache[$name];
    }

    private function _getDecodedBody($endpoint, $accessToken = null, $eTag = null, $graphVersion = null)
    {
        try
        {
            $response = $this->_fb->get($endpoint, $accessToken, $eTag, $graphVersion);
            $decodedBody = $response->getDecodedBody();
        }
        catch(Facebook\Exceptions\FacebookResponseException $e)
        {
            return $this->_FacebookResponseException($e);
        }
        catch(Facebook\Exceptions\FacebookSDKException $e)
        {
            return $this->_FacebookSDKException($e);
        }

        return $decodedBody;
    }

    private function _FacebookResponseException($e)
    {
        // When Graph returns an error
        return array(
            'error' => array(
                'message' => '(#2) Graph returned an error: ' . $e->getMessage(),
                'type' => 'FacebookResponseException',
                'code' => 2,
            )
        );
    }

    private function _FacebookSDKException($e)
    {
        // When validation fails or other local issues
        return array(
            'error' => array(
                'message' => '(#3) Facebook SDK returned an error: ' . $e->getMessage(),
                'type' => 'FacebookSDKException',
                'code' => 3,
            )
        );
    }

    private function _getFileName($sPath)
    {
        $aPath = explode('?', $sPath);
        return basename($aPath[0]);
    }
}
