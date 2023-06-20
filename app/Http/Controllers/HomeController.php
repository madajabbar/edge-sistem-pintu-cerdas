<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseFormatter;
use App\Models\Access;
use App\Models\AccessUser;
use App\Models\Log;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class HomeController extends Controller
{
    public function index()
    {
        // Fetch the JSON data from the URL and decode it
        try {
            Artisan::call('migrate:fresh');
            $jsonurl = "http://pintucerdas.my.id/api/room";
            $json = file_get_contents($jsonurl);
            $room = json_decode($json);
            foreach ($room as $key) {
                Room::create(
                    [
                        'id' => $key->id,
                        'name' => $key->name,
                        'slug' => $key->slug,
                        'ip_address' => $key->ip_address,
                    ]
                );
            }

            $jsonurl = "http://pintucerdas.my.id/api/access";
            $json = file_get_contents($jsonurl);
            $access = json_decode($json);
            foreach ($access as $key) {
                Access::create(
                    [
                        'id' => $key->id,
                        'name' => $key->name,
                        'day' => $key->day,
                        'start_at' => $key->start_at,
                        'end_at' => $key->end_at,
                        'unique_key' => $key->unique_key,
                        'slug' => $key->slug,
                        'room_id' => $key->room_id,
                    ]
                );
            }

            $jsonurl = "http://pintucerdas.my.id/api/getuser";
            $json = file_get_contents($jsonurl);
            $user = json_decode($json);
            foreach ($user as $key) {
                User::create(
                    [
                        'id' => $key->id,
                        'name' => $key->name,
                        'unique_key' => $key->unique_key,
                    ]
                );
            }

            $jsonurl = "http://pintucerdas.my.id/api/accessuser";
            $json = file_get_contents($jsonurl);
            $accessuser = json_decode($json);
            foreach ($accessuser as $key) {
                // dd($key->id);
                AccessUser::create(
                    [
                        'id' => $key->id,
                        'user_id' => $key->user_id,
                        'access_id' => $key->access_id,
                    ]
                );
            }
            return ResponseFormatter::success([
                'room' => $room,
                'access' => $access,
                'user' => $user,
            ], 'data created successfully');
        } catch (Exception $e) {
            return ResponseFormatter::error(
                [
                    'error' => $e->getMessage(),
                    'details' => $e->getTrace(),
                ],
                'some error occurred'
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $ruangan_id = $request->room_id;
            $str = $request->link;
            $expld = explode('-', $str);
            $user = User::where('unique_key', $expld[0])->first();
            if ($user ? $user->name == 'admin' : false) {
                $access = Access::where('room_id', $ruangan_id)->first();
                $url = 'http://pintucerdas.my.id/api/get';
                $data = [
                    'access_id' => $access->id,
                    'user_id' => $user->id,
                ];
                // dd($data);
                $statusCode = 0;
                $client = new Client();
                try {
                    $response = $client->post($url, [
                        'form_params' => $data
                    ]);
                    $check_log = Log::orderBy('id', 'DESC')->first();
                    if ($check_log == null) {
                        $id = 1;
                    } else {
                        $id = $check_log->id + 1;
                    }
                    $data = Log::Create(
                        [
                            'id' => $id,
                            'access_id' => $access->id,
                            'user_id' => $user->id,
                            'status' => 'success'
                        ]
                    );
                    $statusCode = $response->getStatusCode();
                    $check_pending = Log::where('status', 'pending')->get();
                    foreach ($check_pending as $key => $value) {
                        $data = [
                            'access_id' => $value->access_id,
                            'user_id' => $value->user_id,
                        ];
                        $client->post($url, [
                            'form_params' => $data
                        ]);
                        Log::where('id', $value->id)->update([
                            'status' => 'success'
                        ]);
                    }
                    return ResponseFormatter::success($data, 'Upload Successfully');
                } catch (Exception $e) {
                    $check_log = Log::orderBy('id', 'DESC')->first();
                    if ($check_log == null) {
                        $id = 1;
                    } else {
                        $id = $check_log->id + 1;
                    }
                    $data = Log::Create(
                        [
                            'id' => $id,
                            'access_id' => $access->id,
                            'user_id' => $user->id,
                            'status' => 'pending'
                        ]
                    );
                    return ResponseFormatter::success([
                        'data' => $data,
                        'errors' => [
                            'message' => $e->getMessage(),
                            'details' => $e->getTrace(),
                        ]
                    ], 'Upload Success but cloud server has trouble');
                }
            }
            if ($user == null) {
                return ResponseFormatter::error(null, 'User Not Found');
            }
            // $arr = Access::whereIn('unique_key', $expld)->where('room_id', $ruangan_id)->where('day', Carbon::now()->format('l'))->where('start_at', '<', Carbon::now()->format('H:i:s'))->where('end_at', '>', Carbon::now()->format('H:i:s'))->first();
            // $arr = Access::whereIn('unique_key', $expld)->where('room_id', $ruangan_id)->first();
            // dd($arr);
            $arr = [];
            foreach ($user->access as $key) {
                if ($key->start_at <= Carbon::now()->format('H:i:s') && $key->end_at >= Carbon::now()->format('H:i:s') && $key->day == Carbon::now()->format('l') && $key->room_id == $ruangan_id) {
                    array_push($arr, $key);
                }
            }
            if ($arr == null) {
                return ResponseFormatter::error(
                    'Invalid QR, You have no no access',
                    'You have no access in this room yet'
                );
            }
            try {
                $url = 'http://pintucerdas.my.id/api/get';
                $data = [
                    'access_id' => $arr[0]['id'],
                    'user_id' => $user->id,
                ];
                // dd($data);
                $statusCode = 0;
                $client = new Client();
                $response = $client->post($url, [
                    'form_params' => $data
                ]);
                $check_log = Log::orderBy('id', 'DESC')->first();
                if ($check_log == null) {
                    $id = 1;
                } else {
                    $id = $check_log->id + 1;
                }
                $data = Log::Create(
                    [
                        'id' => $id,
                        'access_id' => $arr[0]['id'],
                        'user_id' => $user->id,
                        'status' => 'success'
                    ]
                );
                $statusCode = $response->getStatusCode();
                $check_pending = Log::where('status', 'pending')->get();
                foreach ($check_pending as $key => $value) {
                    $data = [
                        'access_id' => $value->access_id,
                        'user_id' => $value->user_id,
                    ];
                    $client->post($url, [
                        'form_params' => $data
                    ]);
                    Log::where('id', $value->id)->update([
                        'status' => 'success'
                    ]);
                }
                return ResponseFormatter::success($data, 'Upload Successfully');
            } catch (Exception $e) {
                $check_log = Log::orderBy('id', 'DESC')->first();
                if ($check_log == null) {
                    $id = 1;
                } else {
                    $id = $check_log->id + 1;
                }
                $data = Log::Create(
                    [
                        'id' => $id,
                        'access_id' => $arr[0]['id'],
                        'user_id' => $user->id,
                        'status' => 'pending'
                    ]
                );
                return ResponseFormatter::success($data, 'Upload Success but cloud server has trouble');
            }
        } catch (Exception $e) {
            return ResponseFormatter::error(
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ],
                'some error occurred'
            );
        }
    }

    public function test(Request $request)
    {
        $access = Access::where('room_id', 1)
            ->where('day', Carbon::now()->format('l'))
            ->where('start_at', '<=', Carbon::now()->format('H:i:s'))
            ->where('end_at', '>=', Carbon::now()->format('H:i:s'))
            ->get();
        $user = User::where('unique_key', '$2y$10$LjW4eGc.wTult/zKPiqOkuLJlZO2KT3fxcP0iZfSxkX8r6H60TnBO')->first();
        $test = [];
        if($user == null){
            return ResponseFormatter::error(
                [
                    'error' => 'user not found',
                ],
                'some error occurred'
            );
        }
        foreach ($user->access as $key) {
            if ($key->start_at <= Carbon::now()->format('H:i:s') && $key->end_at >= Carbon::now()->format('H:i:s') && $key->day == Carbon::now()->format('l') && $key->room_id == 1) {
                array_push($test, $key);
            }
        }
        if ($test == null) {
            return ResponseFormatter::error(
                [
                    'error' => 'You have no access in this room yet',
                    'trace' => $access->first()->trace,
                ],
                'some error occurred'
            );
        }
        return ResponseFormatter::success(
            [
                'test' => $test
            ]
        );
    }
}
