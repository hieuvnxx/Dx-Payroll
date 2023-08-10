<?php


namespace Dx\Payroll\Http\Controllers\Api;

use Dx\Payroll\Http\Controllers\Controller as Controller;

/**
 * Base function for API Controller 
 */
class BaseController extends Controller
{
    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendResponse($request, $message, $details = [], $code = 200)
    {
        return response()->json([
            'response' => [
                'result' => $details,
            ],
            "uri" => $request->path(),
            'message' => !empty($message) ? $message : "Successfully.",
            'status' => 0,
        ], $code);
    }


    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($request, $message, $details = [], $code = 404)
    {
        return response()->json([
            'message' => "Error occurred.",
            "uri" => $request->path(),
            'errors' => [
                'code' => $code,
                'message' => !empty($message) ? "Error occurred. $message" : "Error occurred.",
                'details' => $details,
            ],
            'status' => 1,
        ], $code);
    }
}
