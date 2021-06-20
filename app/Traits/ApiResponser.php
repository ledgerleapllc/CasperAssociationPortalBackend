<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait ApiResponser
{
    /**
     * Return success Response
     *
     * @param Object     $data data to response
     * @param statusCode $code status code for response
     *
     * @return \Illuminate\Http\Response
     */
    protected function successResponse($data, $code = 200)
    {
        return response()->json([
            'message' => __('api.successfully'),
            'code' => $code,
            'data' => $data,
        ], $code);
    }

    /**
     * Response access token.
     *
     * @param String $token     token
     * @param Array  $info      info
     * @param String $tokenType tokenType
     *
     * @return \Illuminate\Http\Response
     */
    public function responseToken($token, $info = [], $tokenType = 'Bearer')
    {
        $payload = array_merge([
            'token_type' => $tokenType,
            'access_token' => $token->accessToken,
        ], $info);

        return $this->successResponse($payload);
    }

    /**
     * Return error Response
     *
     * @param Object     $message data to response
     * @param statusCode $code    status code for response
     *
     * @return \Illuminate\Http\Response
     */
    protected function errorResponse($message, $code, $error = '')
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'error' => $error,
            'data' => [
                'status' => $code,
            ],

        ], $code);
    }

    /**
     * Response delete data
     *
     * @param int $code response status
     *
     * @return \Illuminate\Http\Response
     */
    protected function metaSuccess($code = 200)
    {
        return response()->json([
            'message' => __('api.successfully'),
            'code' => $code,
            'data' => [
                'status' => $code,
            ],

        ], $code);
    }

    /**
     * Return validate Response
     *
     * @param Object     $errors errors to response
     * @param statusCode $code   status code for response
     *
     * @return \Illuminate\Http\Response
     */
    protected function validateResponse($errors, $code = 422)
    {
        return response()->json([
            'message' => __('api.error.unprocessable_entity'),
            'code' => $code,
            'data' => [
                'status' => $code,
                'errors' => $errors,
            ],
        ], $code);
    }
}
