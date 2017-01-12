<?php

namespace derekisbusy\filemanager\handlers;

class UploadManagerHandlerRBAC extends BaseHandlerRBAC {
    /**
     * 
     * @param \derekisbusy\filemanager\events\FileManagerEvent $event
     */
    function begin($event)
    {
        if(!$this->canView() && !$this->canUpload())
            throw new \yii\web\ForbiddenHttpException();
    }
    
}