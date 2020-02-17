<?php

namespace pso\yii2\wallet\models;

use yii\base\Model;

class WalletTransfer extends Model
{
    public $reference;
    public $value;

    protected $debit;
    protected $credit;

    public function rules(){
        return [
            [['reference','value'],'required']
        ];
    }

    public function init(){
        parent::init();
        $this->debit = new WalletInstruction(WalletInstruction::TYPE_DEBIT);
        $this->credit = new WalletInstruction(WalletInstruction::TYPE_CREDIT);
    }

    public function from(array $from){
        $this->debit->setAttributes($from);
        return $this;
    }

    public function to(array $to){
        $this->credit->setAttributes($to);
        return $this;
    }

    private function ensureDefaults(){
        $this->debit->value = $this->credit->value = $this->value;
        $this->debit->reference = $this->credit->reference = $this->reference;
        if(!$this->credit->tag){
            $this->credit->tag = 'transfer';
        }
        if(!$this->debit->tag){
            $this->debit->tag = 'transfer';
        }
        if(!$this->credit->narration){
            $this->credit->narration = 'Wallet to Wallet Transfer';
        }
        if(!$this->debit->narration){
            $this->debit->narration = 'Wallet to Wallet Transfer';
        }
    }

    public function getCredit(){
        return $this->credit;
    }

    public function getDebit(){
        return $this->debit;
    }

    public function send(){
        if(!$this->validate()){
            return false;
        }
        $this->ensureDefaults();
        $db = Wallet::getDb();
        $transaction  = $db->getTransaction();
        if(is_null($transaction)){
            $isolated = true;
            $transaction = $db->beginTransaction(\yii\db\Transaction::SERIALIZABLE);
        }
        try {
            $sent = $this->debit->send();
            if($sent){
                $sent = $this->credit->send();
            }
            if(!$sent){
                if($isolated){
                    $transaction->rollBack();
                }
                return false;
            }
            if($isolated){
                $transaction->commit();
            }
            return true;
        } catch (\Throwable $th) {
            if($isolated){
                $transaction->rollBack();
            }
            throw $th;
        }
    }
}