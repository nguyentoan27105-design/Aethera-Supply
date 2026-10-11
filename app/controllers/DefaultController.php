<?php
class DefaultController
{
    public function index() {
        header("Location: /webbanhang/product");
        exit();
    }
}
