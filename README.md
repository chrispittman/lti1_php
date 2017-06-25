# lti1_php
Composer package for LTI 1.x interactions in PHP

## Usage example

Here's a sample Laravel controller which uses this to launch an LTI app.

    
    namespace App\Http\Controllers;
    
    use Illuminate\Http\Request;
    use Chrispittman\Lti1\LTIUtils;
    use App;
    use Cache;
    use Redirect;
    use Session;
    
    class LTIController extends Controller
    {
        // assumes these routes are present:
        // Route::post('/lti_launch', 'LTIController@postLaunch');
        // Route::get('/lti_redirect', 'LTIController@getRedirect');
        // Route::get('/lti_tool', 'LTIController@getTool');

        public function postLaunch(Request $request) {
            $lti_utils = new LTIUtils();
            if (!$lti_utils->is_basic_lti_request()) {
                App::abort(403,"Not a basic LTI request.");
            }
            $consumer_key = $request->get('oauth_consumer_key');
            $shared_secret = STORED_SHARED_SECRET_HERE;
            if (!$lti_utils->is_valid_oauth_signature($consumer_key, $shared_secret, $request->url())) {
                App::abort(403,"Invalid OAuth signature.");
            }
    
            $request->session()->put('lti_launch_data', $_POST);
    
            $uuid = self::gen_uuid();
            Cache::put('lti_launch_data_' . $uuid, $_POST, 5);
    
            $launch_url = "/lti_redirect?uuid=".$uuid;
            return Redirect::to($launch_url);
        }
    
        public function getRedirect(Request $request) {
            // * What is this?
            // * Why not just use the data in the session?
            // * Why not just redirect straight to the tool?
            // Sessions are tricky with iframes, which many LMSes put tools inside of:
            // http://www.dr-chuck.com/csev-blog/2013/03/lti-frames-and-cookies-oh-my/
            // ...so we use the cache as a temporary holding area until we have a working session,
            // popping open a new window if needed.
            $uuid = $_REQUEST['uuid'];
            $tool_start_page = "/lti_tool";
            if (Session::has('lti_launch_data')) {
                return Redirect::to('/lti_tool');
            }
            if ($request->has('newwin')) {
                Session::put('lti_launch_data', Cache::get('lti_launch_data_' . $uuid));
                return Redirect::to('/lti_tool');
    
            }
            return "<html><body><br><br><br><div style='text-align:center;font-family:sans-serif;font-size:200%;'><a href='".$request->fullUrl()."&newwin=true' target='_blank'>Click here to load this page.</a></div></body></html>";
        }
    
        public function getTool(Request $request) {
            return "Here's the first page of the tool. <br><br> ".json_encode(Session::get('lti_launch_data'));
        }
    
        protected static function gen_uuid() {
            return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
                // 16 bits for "time_mid"
                mt_rand( 0, 0xffff ),
                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand( 0, 0x0fff ) | 0x4000,
                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand( 0, 0x3fff ) | 0x8000,
                // 48 bits for "node"
                mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
            );
        }
    }
