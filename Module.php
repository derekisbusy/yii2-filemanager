<?php

namespace derekisbusy\filemanager;

use Yii;

use derekisbusy\filemanager\models\Mediafile;

class Module extends \yii\base\Module
{
    public $controllerNamespace = 'derekisbusy\filemanager\controllers';

    /**
     *  Set true if you want to rename files if the name is already in use 
     * @var bolean 
     */
    public $rename = false;
    
     /**
     *  Set true to enable autoupload
     * @var bolean 
     */
    public $autoUpload = false;
    
    /**
     * @var array upload routes
     */
    public $routes = [];
    
    public $relations = [];

    /**
     * @var array thumbnails info
     */
    public $thumbs = [
        'small' => [
            'name' => 'Small size',
            'size' => [120, 80],
        ],
        'medium' => [
            'name' => 'Medium size',
            'size' => [400, 300],
        ],
        'large' => [
            'name' => 'Large size',
            'size' => [800, 600],
        ],
    ];

    /**
     * @var array default thumbnail size, using in filemanager view.
     */
    private static $defaultThumbSize = [128, 128];
    
    /**
     *
     * @var array default route values
     */
    public $defaultRoutes = [
        // base absolute path to web directory
        'baseUrl' => '',
        // base web directory url
        'basePath' => '@webroot',
        // path for uploaded files in web directory
        'uploadPath' => 'uploads',
        // path for uploaded files when using related tables and no Id exist
        'tempPath' => 'temp',
    ];
    
    public $defaultRelation = [
        'canonical' => false,
        'file_id' => 'file_id',
        'folder' => false,
    ];

    public function init()
    {
        parent::init();
        $this->registerTranslations();
        $this->routes = array_merge($this->defaultRoutes,$this->routes);
    }

    public function registerTranslations()
    {
        Yii::$app->i18n->translations['modules/filemanager/*'] = [
            'class' => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en-US',
            'basePath' => '@vendor/derekisbusy/yii2-filemanager/messages',
            'fileMap' => [
                'modules/filemanager/main' => 'main.php',
            ],
        ];
    }

    public static function t($category, $message, $params = [], $language = null)
    {
        if (!isset(Yii::$app->i18n->translations['modules/filemanager/*'])) {
            return $message;
        }

        return Yii::t("modules/filemanager/$category", $message, $params, $language);
    }

    /**
     * @return array default thumbnail size. Using in filemanager view.
     */
    public static function getDefaultThumbSize()
    {
        return self::$defaultThumbSize;
    }
    
    /**
     * Moves the files from a temporary folder to a folder for the related item.
     * 
     * @param type $related
     * @param type $itemId
     * @param type $tempId
     * @param type $defaultImage
     * @return type
     * @throws \yii\web\BadRequestHttpException
     */
    public function assignTempToItem($related,$itemId,$tempId,$defaultImage=null)
    {
        if(!isset($this->relations[$related]) || (!$itemId && !$tempId))
            throw new \yii\web\BadRequestHttpException;
        // move files to item folder
        foreach(Mediafile::find()->where(['temp_id'=>$tempId])->each() as $file) {
            if($file->url==$defaultImage) {
                $file->moveTempFileToItemFolder($this->routes,$related,$itemId,$tempId);
                $defaultImage = $file->url;
            } else {
                $file->moveTempFileToItemFolder($this->routes,$related,$itemId,$tempId);
            }
            $file->createThumbs($this->routes,$this->thumbs);
            // create relation
            $thru = new $this->relations[$related]['class']();
            $thru->{$this->relations[$related]['model_id']} = $itemId;
            $thru->{$this->relations[$related]['file_id']} = $file->id;
            $thru->save();
        }
        // remove temp directory
        $uploadPath = $this->routes['uploadPath'];
        $uploadPath .= '/'.$this->routes['tempPath'].'/'.$tempId;
        $structure = "{$this->routes['baseUrl']}/{$uploadPath}";
        $basePath = Yii::getAlias($this->routes['basePath']);
        $dir = $basePath.$structure;
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it,
                     \RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
        return $defaultImage;
    }
    
    public function copyItemFolder($related,$from,$to)
    {
        $uploadPath = $this->routes['uploadPath'];
        $uploadPath .= '/'.$related;
        $structure = "{$this->routes['baseUrl']}/{$uploadPath}";
        $basePath = Yii::getAlias($this->routes['basePath']);
        $fromPath = "$basePath/$structure/$from";
        $toPath = "$basePath/$structure/$to";
        \yii\helpers\BaseFileHelper::copyDirectory($fromPath, $toPath);
    }
    
    public function removeItemFolder($related,$itemId)
    {
        $uploadPath = $this->routes['uploadPath'];
        $uploadPath .= '/'.$related;
        $structure = "{$this->routes['baseUrl']}/{$uploadPath}";
        $basePath = Yii::getAlias($this->routes['basePath']);
        $dir = "$basePath{$structure}/$itemId";
        \yii\helpers\BaseFileHelper::removeDirectory($dir);
    }
    
    /**
     * Updates all the Ids and copies the files to a new folder.
     * 
     * @param type $files
     * @param type $related
     * @param type $newId
     * @param type $rename
     * @param type $canonical
     */
    public function updateItemIds($files,$related,$newId,$canonical=true)
    {
        foreach($files as $file) {
            $file->moveItemFolder($this->routes,$related,$newId,$canonical);
            $file->createThumbs($this->routes,$this->thumbs);
            $thru = new $this->relations[$related]['class']();
            $thru->{$this->relations[$related]['model_id']} = $newId;
            $thru->{$this->relations[$related]['file_id']} = $file->id;
            $thru->save();
        }
    }
    
    /**
     * Updates all the Ids and copies the files to a new folder.
     * 
     * @param models\Mediafile $files
     * @param type $related
     * @param type $newId
     * @param type $rename
     * @param type $canonical
     */
    public function copyItemFiles($files,$related,$oldId,$newId,$canonical=true)
    {
        foreach($files as $file) {
            $this->copyItemFolder($related, $oldId, $newId);
            $newFile = clone $file;
            $newFile->id = null;
            $newFile->isNewRecord = true;
            $newFile->updateUrl($this->routes, $canonical, $related, $newId);
            $newFile->updateThumbUrls($this->thumbs, $this->routes, $canonical, $related, $newId);
            $newFile->save();
            $thru = new $this->relations[$related]['class']();
            $thru->{$this->relations[$related]['model_id']} = $newId;
            $thru->{$this->relations[$related]['file_id']} = $newFile->id;
            $thru->save();
        }
    }
    
    public function getRelation($name)
    {
        return array_merge($this->defaultRelation,$this->relations[$name]);
    }
}
