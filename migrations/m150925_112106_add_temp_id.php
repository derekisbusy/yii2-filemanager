<?php

use yii\db\Migration;

class m150925_112106_add_temp_id extends Migration
{
    public function up()
    {
        $this->execute('ALTER TABLE :table ADD `temp_id` VARCHAR(64) NULL, ADD INDEX (`temp_id`)',
            [':table'=>\derekisbusy\filemanager\models\Mediafile::tableName()]);
    }

    public function down()
    {
        $this->dropIndex('temp_id', \derekisbusy\filemanager\models\Mediafile::tableName());
        $this->dropColumn(\derekisbusy\filemanager\models\Mediafile::tableName(), 'temp_id');
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}
