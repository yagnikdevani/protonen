<?php
/**
 * Controller genrated using LaraAdmin
 * Help: http://laraadmin.com
 */

namespace App\Http\Controllers\LA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests;
use Auth;
use DB;
use File;
use Validator;
use Datatables;
use Collective\Html\FormFacade as Form;
use Dwij\Laraadmin\Models\Module;
use Dwij\Laraadmin\Models\ModuleFields;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response as FacadeResponse;
use Dwij\Laraadmin\Helpers\LAHelper;

use App\Models\Upload;

class UploadsController extends Controller
{
	public $show_action = true;
	public $view_col = 'name';
	public $listing_cols = ['id', 'name', 'path', 'extension', 'caption', 'user_id', 'hash', 'public'];
	
	public function __construct() {
		// Field Access of Listing Columns
		$this->middleware('auth', ['except' => 'get_file']);
		if(\Dwij\Laraadmin\Helpers\LAHelper::laravel_ver() == 5.3) {
			$this->middleware(function ($request, $next) {
				$this->listing_cols = ModuleFields::listingColumnAccessScan('Uploads', $this->listing_cols);
				return $next($request);
			});
		} else {
			$this->listing_cols = ModuleFields::listingColumnAccessScan('Uploads', $this->listing_cols);
		}
	}
	
	/**
	 * Display a listing of the Uploads.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index()
	{
		$module = Module::get('Uploads');
		
		if(Module::hasAccess($module->id)) {
			return View('la.uploads.index', [
				'show_actions' => $this->show_action,
				'listing_cols' => $this->listing_cols,
				'module' => $module
			]);
		} else {
            return redirect(config('laraadmin.adminRoute')."/");
        }
	}


    /**
     * Get file
     *
     * @return \Illuminate\Http\Response
     */
    public function get_file($hash, $name)
    {
        $upload = Upload::where("hash", $hash)->first();
        
        // Validate Upload Hash & Filename
        if(!isset($upload->id) || $upload->name != $name) {
            return response()->json([
                'status' => "failure",
                'message' => "Unauthorized Access 1"
            ]);
        }
        if($upload->public == 1) {
            $upload->public = true;
        } else {
            $upload->public = false;
        }
        // Validate if Image is Public
        if(!$upload->public && !isset(Auth::user()->id)) {
            return response()->json([
                'status' => "failure",
                'message' => "Unauthorized Access 2",
            ]);
        }
        if($upload->public || Auth::user()->hasRole("Super Admin") || Auth::user()->id == $upload->user_id) {
            
            $path = $upload->path;
            if(!File::exists($path))
                abort(404);
            
            // Check if thumbnail
            $size = Input::get('s');
            if(isset($size)) {
                if(!is_numeric($size)) {
                    $size = 150;
                }
                $thumbpath = storage_path("thumbnails/".basename($upload->path)."-".$size."x".$size);
                
                if(File::exists($thumbpath)) {
                    $path = $thumbpath;
                } else {
                    // Create Thumbnail
                    LAHelper::createThumbnail($upload->path, $thumbpath, $size, $size, "transparent");
                    $path = $thumbpath;
                }
            }
            $file = File::get($path);
            $type = File::mimeType($path);
            $download = Input::get('download');
            if(isset($download)) {
                return response()->download($path, $upload->name);
            } else {
                $response = FacadeResponse::make($file, 200);
                $response->header("Content-Type", $type);
            }
            
            return $response;
        } else {
            return response()->json([
                'status' => "failure",
                'message' => "Unauthorized Access 3"
            ]);
        }
    }

	/**
	 * Show the form for creating a new upload.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create()
	{
		//
	}

	 /**
     * Upload fiels via DropZone.js
     *
     * @return \Illuminate\Http\Response
     */
    public function upload_files() {
        
        $input = Input::all();
        
        if(Input::hasFile('file')) {
            /*
            $rules = array(
                'file' => 'mimes:jpg,jpeg,bmp,png,pdf|max:3000',
            );
            $validation = Validator::make($input, $rules);
            if ($validation->fails()) {
                return response()->json($validation->errors()->first(), 400);
            }
            */
            $file = Input::file('file');
            
            // print_r($file);
            
            $folder = storage_path('uploads');
            $filename = $file->getClientOriginalName();
            $date_append = date("Y-m-d-His-");
            $upload_success = Input::file('file')->move($folder, $date_append.$filename);
            
            if( $upload_success ) {
                // Get public preferences
                // config("laraadmin.uploads.default_public")
                $public = Input::get('public');
                if(isset($public)) {
                    $public = true;
                } else {
                    $public = false;
                }
                $upload = Upload::create([
                    "name" => $filename,
                    "path" => $folder.DIRECTORY_SEPARATOR.$date_append.$filename,
                    "extension" => pathinfo($filename, PATHINFO_EXTENSION),
                    "caption" => "",
                    "public" => $public,
                    "user_id" => Auth::user()->id
                ]);
                // apply unique random hash to file
                while(true) {
                    $hash = strtolower(str_random(20));
                    if(!Upload::where("hash", $hash)->count()) {
                        $upload->hash = $hash;
                        break;
                    }
                }
                $upload->save();
                return response()->json([
                    "status" => "success",
                    "upload" => $upload
                ], 200);
            } else {
                return response()->json([
                    "status" => "error"
                ], 400);
            }
        } else {
            return response()->json('error: upload file not found.', 400);
        }
    }

      /**
     * Get all files from uploads folder
     *
     * @return \Illuminate\Http\Response
     */
    public function uploaded_files()
    {
        $uploads = array();
        // print_r(Auth::user()->roles);
        if(Auth::user()->hasRole("Super Admin")) {
            $uploads = Upload::all();
        } else {
            if(config('laraadmin.uploads.private_uploads')) {
                // Upload::where('user_id', 0)->first();
                $uploads = Auth::user()->uploads;
            } else {
                $uploads = Upload::all();
            }
        }
        $uploads2 = array();
        foreach ($uploads as $upload) {
            $u = (object) array();
            $u->id = $upload->id;
            $u->name = $upload->name;
            $u->extension = $upload->extension;
            $u->hash = $upload->hash;
            $u->public = $upload->public;
            $u->caption = $upload->caption;
            //$u->user = $upload->user->name;
            
            $uploads2[] = $u;
        }
        
        // $folder = storage_path('/uploads');
        // $files = array();
        // if(file_exists($folder)) {
        //     $filesArr = File::allFiles($folder);
        //     foreach ($filesArr as $file) {
        //         $files[] = $file->getfilename();
        //     }
        // }
        // return response()->json(['files' => $files]);
        return response()->json(['uploads' => $uploads2]);
    }
    /**
     * Update Uploads Caption
     *
     * @return \Illuminate\Http\Response
     */
    public function update_caption()
    {
        $file_id = Input::get('file_id');
        $caption = Input::get('caption');
        
        $upload = Upload::find($file_id);
        if(isset($upload->id)) {
            if($upload->user_id == Auth::user()->id || Auth::user()->hasRole("Super Admin")) {
                // Update Caption
                $upload->caption = $caption;
                $upload->save();
                return response()->json([
                    'status' => "success"
                ]);
            } else {
                return response()->json([
                    'status' => "failure",
                    'message' => "Upload not found"
                ]);
            }
        } else {
            return response()->json([
                'status' => "failure",
                'message' => "Upload not found"
            ]);
        }
    }
    /**
     * Update Uploads Filename
     *
     * @return \Illuminate\Http\Response
     */
    public function update_filename()
    {
        $file_id = Input::get('file_id');
        $filename = Input::get('filename');
        
        $upload = Upload::find($file_id);
        if(isset($upload->id)) {
            if($upload->user_id == Auth::user()->id || Auth::user()->hasRole("Super Admin")) {
                // Update Caption
                $upload->name = $filename;
                $upload->save();
                return response()->json([
                    'status' => "success"
                ]);
            } else {
                return response()->json([
                    'status' => "failure",
                    'message' => "Unauthorized Access 1"
                ]);
            }
        } else {
            return response()->json([
                'status' => "failure",
                'message' => "Upload not found"
            ]);
        }
    }
    /**
     * Update Uploads Public Visibility
     *
     * @return \Illuminate\Http\Response
     */
    public function update_public()
    {
        $file_id = Input::get('file_id');
        $public = Input::get('public');
        if(isset($public)) {
            $public = true;
        } else {
            $public = false;
        }
        
        $upload = Upload::find($file_id);
        if(isset($upload->id)) {
            if($upload->user_id == Auth::user()->id || Auth::user()->hasRole("Super Admin")) {
                // Update Caption
                $upload->public = $public;
                $upload->save();
                return response()->json([
                    'status' => "success"
                ]);
            } else {
                return response()->json([
                    'status' => "failure",
                    'message' => "Unauthorized Access 1"
                ]);
            }
        } else {
            return response()->json([
                'status' => "failure",
                'message' => "Upload not found"
            ]);
        }
    }
    /**
     * Remove the specified upload from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function delete_file()
    {
        $file_id = Input::get('file_id');
        
        $upload = Upload::find($file_id);
        if(isset($upload->id)) {
            if($upload->user_id == Auth::user()->id || Auth::user()->hasRole("Super Admin")) {
                // Update Caption
                $upload->delete();
                return response()->json([
                    'status' => "success"
                ]);
            } else {
                return response()->json([
                    'status' => "failure",
                    'message' => "Unauthorized Access 1"
                ]);
            }
        } else {
            return response()->json([
                'status' => "failure",
                'message' => "Upload not found"
            ]);
        }
    }

	/**
	 * Store a newly created upload in database.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request)
	{
		if(Module::hasAccess("Uploads", "create")) {
		
			$rules = Module::validateRules("Uploads", $request);
			
			$validator = Validator::make($request->all(), $rules);
			
			if ($validator->fails()) {
				return redirect()->back()->withErrors($validator)->withInput();
			}
			
			$insert_id = Module::insert("Uploads", $request);
			
			return redirect()->route(config('laraadmin.adminRoute') . '.uploads.index');
			
		} else {
			return redirect(config('laraadmin.adminRoute')."/");
		}
	}

	/**
	 * Display the specified upload.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id)
	{
		if(Module::hasAccess("Uploads", "view")) {
			
			$upload = Upload::find($id);
			if(isset($upload->id)) {
				$module = Module::get('Uploads');
				$module->row = $upload;
				
				return view('la.uploads.show', [
					'module' => $module,
					'view_col' => $this->view_col,
					'no_header' => true,
					'no_padding' => "no-padding"
				])->with('upload', $upload);
			} else {
				return view('errors.404', [
					'record_id' => $id,
					'record_name' => ucfirst("upload"),
				]);
			}
		} else {
			return redirect(config('laraadmin.adminRoute')."/");
		}
	}

	/**
	 * Show the form for editing the specified upload.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function edit($id)
	{
		if(Module::hasAccess("Uploads", "edit")) {			
			$upload = Upload::find($id);
			if(isset($upload->id)) {	
				$module = Module::get('Uploads');
				
				$module->row = $upload;
				
				return view('la.uploads.edit', [
					'module' => $module,
					'view_col' => $this->view_col,
				])->with('upload', $upload);
			} else {
				return view('errors.404', [
					'record_id' => $id,
					'record_name' => ucfirst("upload"),
				]);
			}
		} else {
			return redirect(config('laraadmin.adminRoute')."/");
		}
	}

	/**
	 * Update the specified upload in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id)
	{
		if(Module::hasAccess("Uploads", "edit")) {
			
			$rules = Module::validateRules("Uploads", $request, true);
			
			$validator = Validator::make($request->all(), $rules);
			
			if ($validator->fails()) {
				return redirect()->back()->withErrors($validator)->withInput();;
			}
			
			$insert_id = Module::updateRow("Uploads", $request, $id);
			
			return redirect()->route(config('laraadmin.adminRoute') . '.uploads.index');
			
		} else {
			return redirect(config('laraadmin.adminRoute')."/");
		}
	}

	/**
	 * Remove the specified upload from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id)
	{
		if(Module::hasAccess("Uploads", "delete")) {
			Upload::find($id)->delete();
			
			// Redirecting to index() method
			return redirect()->route(config('laraadmin.adminRoute') . '.uploads.index');
		} else {
			return redirect(config('laraadmin.adminRoute')."/");
		}
	}
	
	/**
	 * Datatable Ajax fetch
	 *
	 * @return
	 */
	public function dtajax()
	{
		$values = DB::table('uploads')->select($this->listing_cols)->whereNull('deleted_at');
		$out = Datatables::of($values)->make();
		$data = $out->getData();

		$fields_popup = ModuleFields::getModuleFields('Uploads');
		
		for($i=0; $i < count($data->data); $i++) {
			for ($j=0; $j < count($this->listing_cols); $j++) { 
				$col = $this->listing_cols[$j];
				if($fields_popup[$col] != null && starts_with($fields_popup[$col]->popup_vals, "@")) {
					$data->data[$i][$j] = ModuleFields::getFieldValue($fields_popup[$col], $data->data[$i][$j]);
				}
				if($col == $this->view_col) {
					$data->data[$i][$j] = '<a href="'.url(config('laraadmin.adminRoute') . '/uploads/'.$data->data[$i][0]).'">'.$data->data[$i][$j].'</a>';
				}
				// else if($col == "author") {
				//    $data->data[$i][$j];
				// }
			}
			
			if($this->show_action) {
				$output = '';
				if(Module::hasAccess("Uploads", "edit")) {
					$output .= '<a href="'.url(config('laraadmin.adminRoute') . '/uploads/'.$data->data[$i][0].'/edit').'" class="btn btn-warning btn-xs" style="display:inline;padding:2px 5px 3px 5px;"><i class="fa fa-edit"></i></a>';
				}
				
				if(Module::hasAccess("Uploads", "delete")) {
					$output .= Form::open(['route' => [config('laraadmin.adminRoute') . '.uploads.destroy', $data->data[$i][0]], 'method' => 'delete', 'style'=>'display:inline']);
					$output .= ' <button class="btn btn-danger btn-xs" type="submit"><i class="fa fa-times"></i></button>';
					$output .= Form::close();
				}
				$data->data[$i][] = (string)$output;
			}
		}
		$out->setData($data);
		return $out;
	}
}
