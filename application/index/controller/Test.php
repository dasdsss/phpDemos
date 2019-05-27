<?php
namespace app\index\controller;
use think\Controller;
class Test extends Controller{
    public function index(){
        return $this->fetch();
    }

    public function two(){
        return $this->fetch();
    }
}