<?php

namespace pso\yii2\wallet\models;

use Yii;
use pso\yii2\wallet\WalletModule;

/**
 * This is the model class for table "{{%wallet_type}}".
 *
 * @property string $id
 * @property string $name
 * @property string $class
 * @property string $min
 * @property string $max 
 * @property int $can_topup
 * @property string $min_topup 
 * @property string $max_topup 
 * @property string $created_at
 * @property string $updated_at
 *
 * @property Wallet[] $wallets
 * @property User[] $users
 */
class WalletType extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%wallet_type}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name','id'], 'required'],
            ['id', 'unique'],
            [['class'], 'string'],
            [['min', 'max', 'min_topup', 'max_topup'], 'number'],
            [['can_topup'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'class' => 'Class',
            'min' => 'Min', 
            'max' => 'Max', 
            'can_topup' => 'Can Topup',
            'min_topup' => 'Min Topup', 
            'max_topup' => 'Max Topup', 
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWallets()
    {
        return $this->hasMany(Wallet::className(), ['type_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(WalletModule::getUserClass(), ['id' => 'user_id'])->viaTable('{{%wallet}}', ['type_id' => 'id']);
    }

    public static function fetchOptions($class = NULL)
    {
        $query = static::find()->select(['name'])->indexBy('id');
        if(!is_null($class)){
            $query->where(['class' => $class]);
        }
        return $query->column();
    }
}
