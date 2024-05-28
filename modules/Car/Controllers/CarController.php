<?php
namespace Modules\Car\Controllers;

use App\Http\Controllers\Controller;
use Modules\Car\Models\Car;
use Illuminate\Http\Request;
use Modules\Location\Models\Location;
use Modules\Review\Models\Review;
use Modules\Core\Models\Attributes;
use DB;

class CarController extends Controller
{
    protected $carClass;
    protected $locationClass;
    public function __construct()
    {
        $this->carClass = Car::class;
        $this->locationClass = Location::class;
    }

    public function callAction($method, $parameters)
    {
        if(!Car::isEnable())
        {
            return redirect('/');
        }
        return parent::callAction($method, $parameters); // TODO: Change the autogenerated stub
    }
    public function index(Request $request)
    {

        $is_ajax = $request->query('_ajax');
        $list = call_user_func([$this->carClass,'search'],$request);
        $markers = [];
        if (!empty($list)) {
            foreach ($list as $row) {
                $markers[] = [
                    "id"      => $row->id,
                    "title"   => $row->title,
                    "lat"     => (float)$row->map_lat,
                    "lng"     => (float)$row->map_lng,
                    "gallery" => $row->getGallery(true),
                    "infobox" => view('Car::frontend.layouts.search.loop-grid', ['row' => $row,'disable_lazyload'=>1,'wrap_class'=>'infobox-item'])->render(),
                    'marker' => get_file_url(setting_item("car_icon_marker_map"),'full') ?? url('images/icons/png/pin.png'),
                ];
            }
        }
        $limit_location = 15;
        if( empty(setting_item("car_location_search_style")) or setting_item("car_location_search_style") == "normal" ){
            $limit_location = 1000;
        }
        $data = [
            'rows'               => $list,
            'list_location'      => $this->locationClass::where('status', 'publish')->limit($limit_location)->with(['translations'])->get()->toTree(),
            'car_min_max_price' => $this->carClass::getMinMaxPrice(),
            'markers'            => $markers,
            "blank"              => 1,
            "seo_meta"           => $this->carClass::getSeoMetaForPageList()
        ];
        $layout = setting_item("car_layout_search", 'normal');
        if ($request->query('_layout')) {
            $layout = $request->query('_layout');
        }
        if ($is_ajax) {
            return $this->sendSuccess([
                'html'    => view('Car::frontend.layouts.search-map.list-item', $data)->render(),
                "markers" => $data['markers']
            ]);
        }
        $data['attributes'] = Attributes::where('service', 'car')->orderBy("position","desc")->with(['terms'=>function($query){$query->withCount('car');},'translations'])->get();

        if ($layout == "map") {
            $data['body_class'] = 'has-search-map';
            $data['html_class'] = 'full-page';
            return view('Car::frontend.search-map', $data);
        }
        return view('Car::frontend.search', $data);
    }

    public function detail(Request $request, $slug)
    {
        $row = $this->carClass::where('slug', $slug)->with(['location','translations','hasWishList'])->first();;
        if ( empty($row) or !$row->hasPermissionDetailView()) {
            return redirect('/');
        }
        $translation = $row->translateOrOrigin(app()->getLocale());
        $car_related = [];
        $location_id = $row->location_id;
        if (!empty($location_id)) {
            $car_related = $this->carClass::where('location_id', $location_id)->where("status", "publish")->take(4)->whereNotIn('id', [$row->id])->with(['location','translations','hasWishList'])->get();
        }
        $review_list = $row->getReviewList();
        $data = [
            'row'          => $row,
            'translation'       => $translation,
            'car_related' => $car_related,
            'booking_data' => $row->getBookingData(),
            'review_list'  => $review_list,
            'seo_meta'  => $row->getSeoMetaWithTranslation(app()->getLocale(),$translation),
            'body_class'=>'is_single',
            'breadcrumbs'       => [
                [
                    'name'  => __('Car'),
                    'url'  => route('car.search'),
                ],
                [
                    'name'  => $translation->title,
                    'class' => 'active'
                ],
            ],
        ];
        $this->setActiveMenu($row);
        return view('Car::frontend.detail', $data);
    }
}
