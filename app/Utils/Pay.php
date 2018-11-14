<?php

namespace App\Utils;

use App\Models\User;
use App\Models\Code;
use App\Models\Paylist;
use App\Models\Payback;
use App\Services\Config;

class Pay
{
    public static function getHTML($user)
    {
        $driver = Config::get("payment_system");
        switch ($driver) {
            case 'zfbjk':
                return Pay::zfbjk_html($user);
            default:
                return "";
        }
        return null;
    }

    private static function zfbjk_html($user)
    {
        return '
						<code>'.$user->id.'</code>
';
    }



    private static function notify(){
        //系统订单号
        $trade_no = $_POST['pay_no'];
        //交易用户
        $trade_id = strtok($_POST['pay_id'], "@");
        //金额
        $trade_num = $_POST['price'];
        $param = urldecode($_POST['param']);
        $codeq=Code::where("code", "=", $trade_no)->first();
        if($codeq!=null){
            exit('success'); //说明数据已经处理完毕
            return;
        }
        if($param!=Config::get('alipay')||$trade_no==''){ //鉴权失败
            exit('fail');
            return;
        }

        //更新用户账户
        $user=User::find($trade_id);
        $codeq=new Code();
        $codeq->code=$trade_no;
        $codeq->isused=1;
        $codeq->type=-1;
        $codeq->number=$_POST['price'];
        $codeq->usedatetime=date("Y-m-d H:i:s");
        $codeq->userid=$user->id;
        $codeq->save();
        $user->money=$user->money+$trade_num;
        $user->save();
        //更新返利
        if ($user->ref_by!=""&&$user->ref_by!=0&&$user->ref_by!=null) {
            $gift_user=User::where("id", "=", $user->ref_by)->first();
            $gift_user->money=($gift_user->money+($codeq->number*(Config::get('code_payback')/100)));
            $gift_user->save();

            $Payback=new Payback();
            $Payback->total=$trade_num;
            $Payback->userid=$user->id;
            $Payback->ref_by=$user->ref_by;
            $Payback->ref_get=$codeq->number*(Config::get('code_payback')/100);
            $Payback->datetime=time();
            $Payback->save();
        }
        exit('success'); //返回成功 不要删除哦
    }

    private static function zfbjk_callback($request)
    {
        //您在www.zfbjk.com的商户ID
        $alidirect_pid = Config::get("zfbjk_pid");
        //您在www.zfbjk.com的商户密钥
        $alidirect_key = Config::get("zfbjk_key");


        $tradeNo = $request->getParam('tradeNo');
        $Money = $request->getParam('Money');
        $title = $request->getParam('title');
        $memo = $request->getParam('memo');
        $alipay_account = $request->getParam('alipay_account');
        $Gateway = $request->getParam('Gateway');
        $Sign = $request->getParam('Sign');
        if (!is_numeric($title)) {
            exit("fail");
        }
        if (strtoupper(md5($alidirect_pid . $alidirect_key . $tradeNo . $Money . $title . $memo)) == strtoupper($Sign)) {
            $trade = Paylist::where("tradeno", '=', $tradeNo)->first();

            if ($trade != null) {
                exit("success");
            } else {
                $user=User::where('id', '=', $title)->first();
                if ($user == null) {
                    exit("IncorrectOrder");
                }
                $pl = new Paylist();
                $pl->userid=$title;
                $pl->tradeno=$tradeNo;
                $pl->total=$Money;
                $pl->datetime=time();
                $pl->status=1;
                $pl->save();
                $user->money=$user->money+$Money;
                $user->save();

                $codeq=new Code();
                $codeq->code="支付宝充值";
                $codeq->isused=1;
                $codeq->type=-1;
                $codeq->number=$Money;
                $codeq->usedatetime=date("Y-m-d H:i:s");
                $codeq->userid=$user->id;
                $codeq->save();




                if ($user->ref_by!=""&&$user->ref_by!=0&&$user->ref_by!=null) {
                    $gift_user=User::where("id", "=", $user->ref_by)->first();
                    $gift_user->money=($gift_user->money+($codeq->number*(Config::get('code_payback')/100)));
                    $gift_user->save();

                    $Payback=new Payback();
                    $Payback->total=$Money;
                    $Payback->userid=$user->id;
                    $Payback->ref_by=$user->ref_by;
                    $Payback->ref_get=$codeq->number*(Config::get('code_payback')/100);
                    $Payback->datetime=time();
                    $Payback->save();
                }

                if (Config::get('enable_donate') == 'true') {
                    if ($user->is_hide == 1) {
                        Telegram::Send("姐姐姐姐，一位不愿透露姓名的大老爷给我们捐了 ".$codeq->number." 元呢~");
                    } else {
                        Telegram::Send("姐姐姐姐，".$user->user_name." 大老爷给我们捐了 ".$codeq->number." 元呢~");
                    }
                }


                exit("Success");
            }
        } else {
            exit('Fail');
        }
    }

    public static function callback($request)
    {
        $driver = Config::get("payment_system");
        switch ($driver) {
            case 'zfbjk':
                return Pay::zfbjk_callback($request);
            default:
                return "";
        }
        return null;
    }
    public static function pay91_notify($request)
    {
        return Pay::notify();
    }
}
