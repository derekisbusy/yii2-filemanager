<?php


namespace derekisbusy\filemanager\handlers;

class FileManagerHandlerRBAC extends BaseHandlerRBAC {
    /**
     * 
     * @param \derekisbusy\filemanager\events\FileManagerEvent $event
     */
    function begin($event)
    {
        $this->checkIsGuest();
        if($this->canViewAll())
            return;
        $this->canViewOwner();
        throw new \yii\web\ForbiddenHttpException();
    }
    
}