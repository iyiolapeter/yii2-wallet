<?php

namespace pso\yii2\wallet\migrations;

use pso\yii2\base\traits\PsoParamTrait;
use yii\db\Migration;

/**
 * Class m200216_092203_init
 */
class m200216_092203_init_wallet_tables extends Migration
{
    use PsoParamTrait;
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }
        $setForeign = static::getPsoParam('wallet.userForeignKeys', true);
        $user = null;
        if($setForeign){
            $user = static::coalescePsoParams(['wallet.user.table','user.table']);
        }
        $this->createTable('{{%wallet_type}}', [
            'id' => $this->string(32)->unique(),
            'name' => $this->string()->notNull(),
            'class' => "ENUM('system', 'user') NOT NULL DEFAULT 'system'",
            'can_topup' => $this->boolean()->notNull()->defaultValue(0),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultValue(null)->append('ON UPDATE CURRENT_TIMESTAMP'),
        ], $tableOptions);
        $this->addPrimaryKey('pk_wallet_type', '{{%wallet_type}}','id');
        $this->createTable('{{%wallet}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'type_id' => $this->string(32)->notNull(),
            'balance' => $this->decimal(17, 2)->defaultValue(0),
            'can_overdraft' => $this->boolean()->notNull()->defaultValue(0),
            'overdraft_limit' => $this->decimal(17, 2)->null(),
            'status' => "ENUM('active', 'inactive') NOT NULL DEFAULT 'active'",
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultValue(null)->append('ON UPDATE CURRENT_TIMESTAMP'),
        ], $tableOptions);
        if($setForeign){
            $this->addForeignKey('fk_wallet_user','{{%wallet}}', 'user_id', $user,'id','RESTRICT', 'CASCADE');
        }
        $this->addForeignKey('fk_wallet_wallet_type','{{%wallet}}', 'type_id', '{{%wallet_type}}','id','RESTRICT', 'CASCADE');
        $this->createIndex('idx_wallet_user_type', '{{%wallet}}', ['user_id', 'type_id'], true);
        $this->createTable('{{%wallet_transaction}}', [
            'id' => $this->primaryKey(),
            'reference' => $this->string()->notNull(),
            'wallet_id' => $this->integer()->notNull(),
            'type' => "ENUM('cr','dr') NOT NULL",
            'value' => $this->decimal(17, 2)->notNull(),
            'tag' => $this->string(32)->notNull(),
            'narration' => $this->string()->null(),
            'status' => "ENUM('completed', 'partial','reversed') NOT NULL DEFAULT 'completed'",
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultValue(null)->append('ON UPDATE CURRENT_TIMESTAMP'),
        ], $tableOptions);
        $this->addForeignKey('fk_wallet_transaction_wallet','{{%wallet_transaction}}', 'wallet_id', '{{%wallet}}','id','RESTRICT', 'CASCADE');
        $this->createIndex('idx_wallet_transaction_reference_wallet_id_type', '{{%wallet_transaction}}', ['reference', 'wallet_id', 'type'], true);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m200216_092203_init cannot be reverted.\n";
        $this->dropTable('{{%wallet_type}}');
        $this->dropTable('{{%wallet}}');
        $this->dropTable('{{%wallet_transaction}}');
        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m200216_092203_init cannot be reverted.\n";

        return false;
    }
    */
}
