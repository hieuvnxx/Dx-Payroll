<?php

namespace Dx\Payroll\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as Controller;


class EmployeeController extends Controller
{
    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return "test index";
    }


    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
    	return "test store";
    }
}