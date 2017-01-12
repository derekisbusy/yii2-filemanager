<?php


namespace derekisbusy\filemanager\events;


class FileManagerEvent extends \yii\base\Event {
    public $controller;
    public $relation = '';
    public $itemId;
    public $tempId;
    public $defaultPageSize;
    public $query;
    public $view;
}
