<?php

namespace derekisbusy\filemanager\models;

use Yii;
use yii\web\UploadedFile;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\imagine\Image;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\helpers\Inflector;
use derekisbusy\filemanager\Module;
use derekisbusy\filemanager\models\Owners;
use Imagine\Image\ImageInterface;

/**
 * This is the model class for table "filemanager_mediafile".
 *
 * @property integer $id
 * @property string $filename
 * @property string $type
 * @property string $url
 * @property string $alt
 * @property integer $size
 * @property string $description
 * @property string $thumbs
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $temp_id
 * @property Owners[] $owners
 */
class Mediafile extends ActiveRecord
{
    public $file;

    public static $imageFileTypes = ['image/gif', 'image/jpeg', 'image/png'];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'filemanager_mediafile';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['filename', 'type', 'url', 'size'], 'required'],
            [['url', 'alt', 'description', 'thumbs','temp_id'], 'string'],
            [['created_at', 'updated_at', 'size'], 'integer'],
            [['filename', 'type'], 'string', 'max' => 255],
            [['file'], 'file']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Module::t('main', 'ID'),
            'filename' => Module::t('main', 'filename'),
            'type' => Module::t('main', 'Type'),
            'url' => Module::t('main', 'Url'),
            'alt' => Module::t('main', 'Alt attribute'),
            'size' => Module::t('main', 'Size'),
            'description' => Module::t('main', 'Description'),
            'thumbs' => Module::t('main', 'Thumbnails'),
            'created_at' => Module::t('main', 'Created'),
            'updated_at' => Module::t('main', 'Updated'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'created_at',
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'updated_at',
                ],
            ]
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOwners()
    {
        return $this->hasMany(Owners::className(), ['mediafile_id' => 'id']);
    }

    public function beforeDelete()
    {
        if (parent::beforeDelete()) {

            foreach ($this->owners as $owner) {
                $owner->delete();
            }

            return true;
        } else {
            return false;
        }
    }
    
    public function getPaths($routes,$canonical=true,$related=null,$itemId=null)
    {
        $uploadPath = $routes['uploadPath'];
        if($related)
            $uploadPath .= '/'.$related;
        if($itemId) 
            $uploadPath .= '/'.$itemId;
        $structure = "{$routes['baseUrl']}/{$uploadPath}";
        if($canonical) {
            $year = date('Y', time());
            $month = date('m', time());
            $structure .= "/$year/$month";
        }
        $r['uploadPath'] = $uploadPath;
        $r['structure'] = $structure;
        $r['basePath'] = Yii::getAlias($routes['basePath']);
        $r['absolutePath'] = "{$r['basePath']}$structure";
        return $r;
    }
    
    public function moveItemFolder($routes,$related,$itemId,$canonical=false)
    {
        $oldPath = $this->getPaths($routes,$canonical,$related,$itemId);
        $newPath = $this->getPaths($routes,$canonical,$related,$this->id);
        
        if (!file_exists($newPath['absolutePath'])) {
            mkdir($newPath['absolutePath'], 0777, true);
        }
        
        $oldFilePath = $oldPath['absolutePath'].DIRECTORY_SEPARATOR.$this->filename;
        $newFilePath = $newPath['absolutePath'].DIRECTORY_SEPARATOR.$this->filename;
        
        
        // move file to new folder
        rename($oldFilePath, $newFilePath);
        
        // update media record
        $this->url = $newPath['structure'].DIRECTORY_SEPARATOR.$this->filename;
        
        return $this->save();
    }
    
    public function copyItemFolder($routes,$related,$oldId,$newId,$canonical=false)
    {
        $old = $this->getPaths($routes,$canonical,$related,$oldId);
        $new = $this->getPaths($routes,$canonical,$related,$newId);
        
        if (!file_exists($new['absolutePath'])) {
            mkdir($new['absolutePath'], 0777, true);
        }
        
        $oldFilePath = $old['absolutePath'].'/'.$this->filename;
        $newFilePath = $new['absolutePath'].'/'.$this->filename;
//        var_dump($oldFilePath,$newFilePath); exit;
        copy($oldFilePath, $newFilePath);
        return $new['structure'].DIRECTORY_SEPARATOR.$this->filename;
    }

    public function moveTempFileToItemFolder($routes,$related,$itemId,$rename=true,$canonical=false)
    {
        $uploadPath = $routes['uploadPath'];
        $uploadPath .= '/'.$related.'/'.$itemId;
        $structure = "{$routes['baseUrl']}/{$uploadPath}";
        if($canonical) {
            $year = date('Y', time());
            $month = date('m', time());
            $structure .= "/$year/$month";
        }
        $basePath = Yii::getAlias($routes['basePath']);
        $absolutePath = "$basePath/$structure";
        
        // create directory structure if not exists
        if (!file_exists($absolutePath)) {
            mkdir($absolutePath, 0777, true);
        }
        $filePath = $basePath.$this->url;
        $info = pathinfo($filePath);
        //if a file with the same name already exist append a number
        $counter = 0;
        do{
            if($counter==0)
                $filename = Inflector::slug($info['filename']).'.'. $info['extension'];
            else{
                //if we don't want to rename we finish the call here
                if($rename == false)
                    return false;
                $filename = Inflector::slug($info['filename']). $counter.'.'. $info['extension'];
            }
            $newUrl = "$structure/$filename";
            $newPath = "{$basePath}{$structure}/$filename";
            $counter++;
        } while(self::findByUrl($newUrl)); // checks for existing url in db
        
        // move file to new folder
        rename($filePath, $newPath);
        
        
        // update media record
        $this->filename = $filename;
        $this->url = $newUrl;
        $this->temp_id = null;

        return $this->save();
    }
    
    /**
     * Save just uploaded file
     * @param array $routes routes from module settings
     * @return bool
     */
    public function saveUploadedFile(array $routes, $rename = false, $related=null, $itemId=null)
    {
        $year = date('Y', time());
        $month = date('m', time());
        $uploadPath = $routes['uploadPath'];
        $relation = Yii::$app->getModule('filemanager')->getRelation($related);
        if($related && $itemId) {
            $uploadPath .= '/'.$related.'/'.$itemId;
            if($relation['canonical'])
                $uploadPath .= "/$year/$month";
            $structure = "{$routes['baseUrl']}/{$uploadPath}";
        }
        elseif($related && !$itemId) {
            if(!$this->temp_id)
                throw new \yii\web\BadRequestHttpException('Related model set but no item_id or temp_id found');
            $uploadPath .= '/'.$routes['tempPath'].'/'.$this->temp_id;
            $structure = "{$routes['baseUrl']}/{$uploadPath}";
        } else {
            $uploadPath .= "/$year/$month";
        }
        $basePath = Yii::getAlias($routes['basePath']);
        $absolutePath = "$basePath/$structure";

        // create directory structure
        if (!file_exists($absolutePath)) {
            mkdir($absolutePath, 0777, true);
        }

        // get file instance
        $this->file = UploadedFile::getInstance($this, 'file');
        //if a file with the same name already exist append a number
        $counter = 0;
        do{
            if($counter==0)
                $filename = Inflector::slug($this->file->baseName).'.'. $this->file->extension;
            else{
                //if we don't want to rename we finish the call here
                if($rename == false)
                    break;
                $filename = Inflector::slug($this->file->baseName). $counter.'.'. $this->file->extension;
            }
            $url = "$structure/$filename";
            $counter++;
        } while(self::findByUrl($url)); // checks for existing url in db

        // save original uploaded file
        $this->file->saveAs("$absolutePath/$filename");
        $this->filename = $filename;
        $this->type = $this->file->type;
        $this->size = $this->file->size;
        $this->url = $url;

        return $this->save();
    }

    /**
     * Create thumbs for this image
     *
     * @param array $routes see routes in module config
     * @param array $presets thumbs presets. See in module config
     * @return bool
     */
    public function createThumbs(array $routes, array $presets)
    {
        $thumbs = [];
        $basePath = $basePath = Yii::getAlias($routes['basePath']);
        $originalFile = pathinfo($this->url);
        $dirname = $originalFile['dirname'];
        $filename = $originalFile['filename'];
        $extension = $originalFile['extension'];

        Image::$driver = [Image::DRIVER_GD2, Image::DRIVER_GMAGICK, Image::DRIVER_IMAGICK];

        foreach ($presets as $alias => $preset) {
            $width = $preset['size'][0];
            $height = $preset['size'][1];
            $mode = (isset($preset['mode']) ? $preset['mode'] : ImageInterface::THUMBNAIL_OUTBOUND);

            $thumbUrl = "$dirname/$filename-{$width}x{$height}.$extension";

            Image::thumbnail("$basePath/{$this->url}", $width, $height, $mode)->save("$basePath/$thumbUrl");

            $thumbs[$alias] = $thumbUrl;
        }

        $this->thumbs = serialize($thumbs);
        $this->detachBehavior('timestamp');

        // create default thumbnail
        $this->createDefaultThumb($routes);

        return $this->save();
    }

    /**
     * Create default thumbnail
     *
     * @param array $routes see routes in module config
     */
    public function createDefaultThumb(array $routes)
    {
        $originalFile = pathinfo($this->url);
        $dirname = $originalFile['dirname'];
        $filename = $originalFile['filename'];
        $extension = $originalFile['extension'];

        Image::$driver = [Image::DRIVER_GD2, Image::DRIVER_GMAGICK, Image::DRIVER_IMAGICK];

        $size = Module::getDefaultThumbSize();
        $width = $size[0];
        $height = $size[1];
        $thumbUrl = "$dirname/$filename-{$width}x{$height}.$extension";
        $basePath = Yii::getAlias($routes['basePath']);
        Image::thumbnail("$basePath/{$this->url}", $width, $height)->save("$basePath/$thumbUrl");
    }
    
    public function updateUrl(array $routes, $canonical = false, $related = null, $itemId = null)
    {
        $new = $this->getPaths($routes,$canonical,$related,$itemId);
        $this->url = '/'.$new['uploadPath'].'/'.$this->filename;
    }
    
    public function updateThumbUrls(array $presets, array $routes, $canonical = false, $related = null, $itemId = null)
    {
        $originalFile = pathinfo($this->url);
        $dirname = $originalFile['dirname'];
        $filename = $originalFile['filename'];
        $extension = $originalFile['extension'];
        $p = $this->getPaths($routes, $canonical, $related, $itemId);
        foreach ($presets as $alias => $preset) {
            $width = $preset['size'][0];
            $height = $preset['size'][1];
            $thumbUrl = "/{$p['uploadPath']}/$filename-{$width}x{$height}.$extension";
            $thumbs[$alias] = $thumbUrl;
        }
        $this->thumbs = serialize($thumbs);
    }

    /**
     * Add owner to mediafiles table
     *
     * @param int $owner_id owner id
     * @param string $owner owner identification name
     * @param string $owner_attribute owner identification attribute
     * @return bool save result
     */
    public function addOwner($owner_id, $owner, $owner_attribute)
    {
        $mediafiles = new Owners();
        $mediafiles->mediafile_id = $this->id;
        $mediafiles->owner = $owner;
        $mediafiles->owner_id = $owner_id;
        $mediafiles->owner_attribute = $owner_attribute;

        return $mediafiles->save();
    }

    /**
     * Remove this mediafile owner
     *
     * @param int $owner_id owner id
     * @param string $owner owner identification name
     * @param string $owner_attribute owner identification attribute
     * @return bool delete result
     */
    public static function removeOwner($owner_id, $owner, $owner_attribute)
    {
        $mediafiles = Owners::findOne([
            'owner_id' => $owner_id,
            'owner' => $owner,
            'owner_attribute' => $owner_attribute,
        ]);

        if ($mediafiles) {
            return $mediafiles->delete();
        }

        return false;
    }

    /**
     * @return bool if type of this media file is image, return true;
     */
    public function isImage()
    {
        return in_array($this->type, self::$imageFileTypes);
    }

    /**
     * @param $baseUrl
     * @return string default thumbnail for image
     */
    public function getDefaultThumbUrl($baseUrl = '')
    {
        if ($this->isImage()) {
            $size = Module::getDefaultThumbSize();
            $originalFile = pathinfo($this->url);
            $dirname = $originalFile['dirname'];
            $filename = $originalFile['filename'];
            $extension = $originalFile['extension'];
            $width = $size[0];
            $height = $size[1];
            return "$dirname/$filename-{$width}x{$height}.$extension";
        }
        return "$baseUrl/images/file.png";
    }
    /**
     * @param $baseUrl
     * @return string default thumbnail for image
     */
    public function getDefaultUploadThumbUrl($baseUrl = '')
    {
        $size = Module::getDefaultThumbSize();
        $originalFile = pathinfo($this->url);
        $dirname = $originalFile['dirname'];
        $filename = $originalFile['filename'];
        $extension = $originalFile['extension'];
        $width = $size[0];
        $height = $size[1];
        return Yii::getAlias('@web')."$dirname/$filename-{$width}x{$height}.$extension";
    }
    /**
     * @return array thumbnails
     */
    public function getThumbs()
    {
        return unserialize($this->thumbs);
    }

    /**
     * @param string $alias thumb alias
     * @return string thumb url
     */
    public function getThumbUrl($alias)
    {
        $thumbs = $this->getThumbs();

        if ($alias === 'original') {
            return $this->url;
        }

        return !empty($thumbs[$alias]) ? $thumbs[$alias] : '';
    }

    /**
     * Thumbnail image html tag
     *
     * @param string $alias thumbnail alias
     * @param array $options html options
     * @return string Html image tag
     */
    public function getThumbImage($alias, $options=[])
    {
        $url = $this->getThumbUrl($alias);

        if (empty($url)) {
            return '';
        }

        if (empty($options['alt'])) {
            $options['alt'] = $this->alt;
        }

        return Html::img($url, $options);
    }

    /**
     * @param Module $module
     * @return array images list
     */
    public function getImagesList(Module $module)
    {
        $thumbs = $this->getThumbs();
        $list = [];
        $originalImageSize = $this->getOriginalImageSize($module->routes);
        $list[$this->url] = Module::t('main', 'Original') . ' ' . $originalImageSize;

        foreach ($thumbs as $alias => $url) {
            $preset = $module->thumbs[$alias];
            $list[$url] = $preset['name'] . ' ' . $preset['size'][0] . ' × ' . $preset['size'][1];
        }
        return $list;
    }

    /**
     * Delete thumbnails for current image
     * @param array $routes see routes in module config
     */
    public function deleteThumbs(array $routes)
    {
        $basePath = Yii::getAlias($routes['basePath']);

        foreach ($this->getThumbs() as $thumbUrl) {
            unlink("$basePath/$thumbUrl");
        }

        unlink("$basePath/{$this->getDefaultThumbUrl()}");
    }

    /**
     * Delete file
     * @param array $routes see routes in module config
     * @return bool
     */
    public function deleteFile(array $routes)
    {
        $basePath = Yii::getAlias($routes['basePath']);
        return unlink("$basePath/{$this->url}");
    }

    /**
     * Creates data provider instance with search query applied
     * @return ActiveDataProvider
     */
    public function search($query=null)
    {
        if(!$query)
            $query = self::find()->orderBy('created_at DESC');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        return $dataProvider;
    }

    /**
     * @return int last changes timestamp
     */
    public function getLastChanges()
    {
        return !empty($this->updated_at) ? $this->updated_at : $this->created_at;
    }

    /**
     * This method wrap getimagesize() function
     * @param array $routes see routes in module config
     * @param string $delimiter delimiter between width and height
     * @return string image size like '1366x768'
     */
    public function getOriginalImageSize(array $routes, $delimiter = ' × ')
    {
        $imageSizes = $this->getOriginalImageSizes($routes);
        return "$imageSizes[0]$delimiter$imageSizes[1]";
    }

    /**
     * This method wrap getimagesize() function
     * @param array $routes see routes in module config
     * @return array
     */
    public function getOriginalImageSizes(array $routes)
    {
        $basePath = Yii::getAlias($routes['basePath']);
        return getimagesize("$basePath/{$this->url}");
    }

    /**
     * @return string file size
     */
    public function getFileSize()
    {
        Yii::$app->formatter->sizeFormatBase = 1000;
        return Yii::$app->formatter->asShortSize($this->size, 0);
    }

    /**
     * Find model by url
     *
     * @param $url
     * @return static
     */
    public static function findByUrl($url)
    {
        return self::findOne(['url' => $url]);
    }

    /**
     * Search models by file types
     * @param array $types file types
     * @return array|\yii\db\ActiveRecord[]
     */
    public static function findByTypes(array $types)
    {
        return self::find()->filterWhere(['in', 'type', $types])->all();
    }

    public static function loadOneByOwner($owner, $owner_id, $owner_attribute)
    {
        $owner = Owners::findOne([
            'owner' => $owner,
            'owner_id' => $owner_id,
            'owner_attribute' => $owner_attribute,
        ]);

        if ($owner) {
            return $owner->mediafile;
        }

        return false;
    }
}
