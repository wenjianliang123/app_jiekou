<?php

namespace App\Http\Tool;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\Index_login;

class common extends Controller
{
    //用curl 写的 比较全面
    public static function curl_get_post_originData($url,$method="GET",$postData=[],$header=[])
    {
        //1初始化
        $ch = curl_init();
        //2设置
        curl_setopt($ch,CURLOPT_URL,$url); //访问地址
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1); //返回格式
        if($method="POST"){
            curl_setopt($ch,CURLOPT_POST, 1); // 发送一个常规的Post请求
            curl_setopt($ch,CURLOPT_POSTFIELDS,$postData); // Post提交的数据包
        }
        if (!empty($header)){
            curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false); // 对认证证书来源的检查
        //3执行
        $content = curl_exec($ch);

        //4关闭
        curl_close($ch);
        return $content;
    }

    //用curl 封装HTTP中的get和post 请求方式 自己写的不成熟 但是功能可以实现
    public function get_post($url,$data='')
    {
        //1初始化
        $ch = curl_init();
        //2设置
        curl_setopt($ch,CURLOPT_URL,$url); //访问地址
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1); //返回格式
        if(empty($data)){
            //get
            //请求网址是https
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false); // 对认证证书来源的检查
            //3执行
            $content = curl_exec($ch);
        }else{
            //post
            curl_setopt($ch,CURLOPT_POST, 1); // 发送一个常规的Post请求
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data); // Post提交的数据包
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false); // 对认证证书来源的检查
            //3执行
            $content = curl_exec($ch);
        }
        //4关闭
        curl_close($ch);
        return $content;
    }
    //获取access_token
    public static function get_access_token()
    {
        $access_token_key='wechat_access_token';
        $redis=new \Redis();
        $redis->connect('127.0.0.1','6379');
        //在方法中判断key
        if($redis->exists($access_token_key))
        {
            //从缓存中拿access_token
            $access_token=$redis->get($access_token_key);
//            echo '这是从缓存中拿到的access_token';
//            dd($access_token);
        }else{
            //如果没有 调用接口拿access_token 并存入redis
            $access_token_info=file_get_contents("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".env('WECHAT_APPID')."&secret=".env('WECHAT_APPSECRET')."");
            $access_token_info=json_decode($access_token_info,1);
//            dd($access_token_info);
            //数组的操作需要json_decode($data,1)变为关联数组
            $access_token=$access_token_info['access_token'];
            $expires_in=$access_token_info['expires_in'];
            $redis->set($access_token_key,$access_token,$expires_in);
        }
        //最终返回一个access_token
        return $access_token;
    }

    //清零微信调用接口次数限制
    public static function empty_api_count()
    {
        $url="https://api.weixin.qq.com/cgi-bin/clear_quota?access_token=".self::get_access_token();
        $data=[
            "appid"=>env('WECHAT_APPID')
        ];
        $re=self::curl_get_post_originData($url,"POST",json_encode($data));
        dd($re);

    }

    //以下是三种无限极分类 在本项目中没有使用以下三种
    //递归千万注意不是无限级分类
    function createLevel($cate_info,$parent_id=0,$level=0){
        static $result=[];
        foreach ($cate_info as $v) {
            if ($v['parent_id']==$parent_id) {
                $v['level']=$level;
                $result[]=$v;
                createLevel($cate_info,$v['id'],$level+1);
            }
        }
        return $result;
    }


    function createTree($data,$parent_id=0,$level=1,$field="id")
    {
        static $result=[];
        $field=$field;
        if($data){
            foreach ($data as $key => $val) {
                if($val['parent_id']==$parent_id){
                    $val['level']=$level;
                    $result[]=$val;
                    //$val['id']    需改id
                    createTree($data,$val[$field],$level+1,$field);
                }
            }
            return $result;
        }
    }
    /*无限极分类
        $result 	存放结果的静态数组
        $parent_id	父级id 默认为0 代表一级
        $date 		要循环的数据
    */
    function createSonTree($data,$parent_id=0)
    {
        $result=[];
        if($data){
            foreach ($data as $key => $val) {
                if($val['parent_id']==$parent_id){
                    $result[$key]=$val;
                    $result[$key]['son']=createSonTree($data,$val['id']);
                }
            }
            return $result;
        }
    }

    //获取前台用户信息
    public static function get_user_info_2($token)
    {
//        $token=$request->token;
        //判断token是否不为空
        if(!empty($token)){
            $token_info=Index_login::where('token',$token)->first();
            //判断token是否正确
            if($token_info){
                //判断超出时间没有
                if(!(time()>$token_info['token_expire'])){
                    $token_info->token_expire=time()+7200;
                    $token_info->save();
                    return json_encode(['code'=>200,'msg'=>'查询成功','data'=>$token_info],JSON_UNESCAPED_UNICODE);
                }else{
                    return json_encode(['code'=>404,'msg'=>'token已过期，请重新登录'],JSON_UNESCAPED_UNICODE);
                }

            }else{
                //没有这个数据
                return json_encode(['code'=>404,'msg'=>'查询失败，token不对'],JSON_UNESCAPED_UNICODE);
            }
        }else{
            return json_encode(['code'=>404,'msg'=>'token不能为空'],JSON_UNESCAPED_UNICODE);
        }
    }



}

