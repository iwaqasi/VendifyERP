<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsController extends Controller
{
    // ============================================================
    // CMS DASHBOARD
    // ============================================================
    public function dashboard()
    {
        $business_id = request()->session()->get('user.business_id');
        
        $stats = [
            'pages' => DB::table('cms_pages')->where('business_id', $business_id)->count(),
            'published_pages' => DB::table('cms_pages')->where('business_id', $business_id)->where('is_published', true)->count(),
            'posts' => DB::table('cms_posts')->where('business_id', $business_id)->count(),
            'published_posts' => DB::table('cms_posts')->where('business_id', $business_id)->where('is_published', true)->count(),
            'banners' => DB::table('cms_banners')->where('business_id', $business_id)->count(),
            'testimonials' => DB::table('cms_testimonials')->where('business_id', $business_id)->count(),
            'faqs' => DB::table('cms_faqs')->where('business_id', $business_id)->count(),
            'media' => DB::table('cms_media')->where('business_id', $business_id)->count(),
        ];

        $recent_posts = DB::table('cms_posts')
            ->where('business_id', $business_id)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $recent_pages = DB::table('cms_pages')
            ->where('business_id', $business_id)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return view('cms.dashboard', compact('stats', 'recent_posts', 'recent_pages'));
    }

    // ============================================================
    // WEBSITE DESIGNER
    // ============================================================
    public function designer()
    {
        $business_id = request()->session()->get('user.business_id');
        $design = DB::table('cms_design_settings')->where('business_id', $business_id)->first();
        
        if (!$design) {
            DB::table('cms_design_settings')->insert([
                'business_id' => $business_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $design = DB::table('cms_design_settings')->where('business_id', $business_id)->first();
        }

        $banners = DB::table('cms_banners')
            ->where('business_id', $business_id)
            ->orderBy('sort_order')
            ->get();

        $sections = DB::table('cms_sections')
            ->where('business_id', $business_id)
            ->orderBy('sort_order')
            ->get();

        return view('cms.designer', compact('design', 'banners', 'sections'));
    }

    public function saveDesign(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $data = $request->all();

        // Handle file uploads
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('cms/logos', 'public');
        }
        if ($request->hasFile('favicon')) {
            $data['favicon'] = $request->file('favicon')->store('cms/favicons', 'public');
        }
        if ($request->hasFile('hero_image')) {
            $data['hero_image'] = $request->file('hero_image')->store('cms/hero', 'public');
        }

        DB::table('cms_design_settings')->updateOrInsert(
            ['business_id' => $business_id],
            array_merge($data, ['updated_at' => now()])
        );

        return response()->json(['success' => true, 'message' => 'Design settings saved successfully']);
    }

    // ============================================================
    // PAGES MANAGEMENT
    // ============================================================
    public function pages()
    {
        $business_id = request()->session()->get('user.business_id');
        $pages = DB::table('cms_pages')
            ->where('business_id', $business_id)
            ->orderByDesc('updated_at')
            ->get();

        return view('cms.pages.index', compact('pages'));
    }

    public function pageCreate()
    {
        return view('cms.pages.create');
    }

    public function pageStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $business_id = request()->session()->get('user.business_id');
        $slug = Str::slug($request->input('title'));

        // Ensure unique slug
        $existing = DB::table('cms_pages')->where('slug', $slug)->where('business_id', $business_id)->first();
        if ($existing) {
            $slug = $slug . '-' . time();
        }

        DB::table('cms_pages')->insert([
            'business_id' => $business_id,
            'slug' => $slug,
            'title' => $request->input('title'),
            'title_ar' => $request->input('title_ar'),
            'content' => $request->input('content'),
            'content_ar' => $request->input('content_ar'),
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'is_published' => $request->boolean('is_published', true),
            'is_homepage' => $request->boolean('is_homepage', false),
            'sort_order' => $request->input('sort_order', 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.pages')->with('success', 'Page created successfully');
    }

    public function pageEdit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $page = DB::table('cms_pages')->where('id', $id)->where('business_id', $business_id)->first();
        
        if (!$page) abort(404);
        
        return view('cms.pages.edit', compact('page'));
    }

    public function pageUpdate(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        DB::table('cms_pages')
            ->where('id', $id)
            ->where('business_id', $business_id)
            ->update([
                'title' => $request->input('title'),
                'title_ar' => $request->input('title_ar'),
                'content' => $request->input('content'),
                'content_ar' => $request->input('content_ar'),
                'meta_title' => $request->input('meta_title'),
                'meta_description' => $request->input('meta_description'),
                'is_published' => $request->boolean('is_published', true),
                'is_homepage' => $request->boolean('is_homepage', false),
                'sort_order' => $request->input('sort_order', 0),
                'updated_at' => now(),
            ]);

        return redirect()->route('cms.pages')->with('success', 'Page updated successfully');
    }

    public function pageDelete($id)
    {
        $business_id = request()->session()->get('user.business_id');
        DB::table('cms_pages')->where('id', $id)->where('business_id', $business_id)->delete();
        return redirect()->route('cms.pages')->with('success', 'Page deleted successfully');
    }

    // ============================================================
    // BLOG POSTS MANAGEMENT
    // ============================================================
    public function posts()
    {
        $business_id = request()->session()->get('user.business_id');
        $posts = DB::table('cms_posts')
            ->where('business_id', $business_id)
            ->orderByDesc('updated_at')
            ->get();

        return view('cms.posts.index', compact('posts'));
    }

    public function postCreate()
    {
        return view('cms.posts.create');
    }

    public function postStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $business_id = request()->session()->get('user.business_id');
        $slug = Str::slug($request->input('title'));

        $existing = DB::table('cms_posts')->where('slug', $slug)->where('business_id', $business_id)->first();
        if ($existing) {
            $slug = $slug . '-' . time();
        }

        // Handle featured image
        $featuredImage = null;
        if ($request->hasFile('featured_image')) {
            $featuredImage = $request->file('featured_image')->store('cms/posts', 'public');
        }

        DB::table('cms_posts')->insert([
            'business_id' => $business_id,
            'slug' => $slug,
            'title' => $request->input('title'),
            'title_ar' => $request->input('title_ar'),
            'excerpt' => $request->input('excerpt'),
            'content' => $request->input('content'),
            'content_ar' => $request->input('content_ar'),
            'featured_image' => $featuredImage,
            'author_name' => $request->input('author_name', auth()->user()->first_name . ' ' . auth()->user()->last_name),
            'category' => $request->input('category'),
            'tags' => $request->input('tags'),
            'is_published' => $request->boolean('is_published', true),
            'published_at' => now(),
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.posts')->with('success', 'Post created successfully');
    }

    public function postEdit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $post = DB::table('cms_posts')->where('id', $id)->where('business_id', $business_id)->first();
        
        if (!$post) abort(404);
        
        return view('cms.posts.edit', compact('post'));
    }

    public function postUpdate(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $updateData = [
            'title' => $request->input('title'),
            'title_ar' => $request->input('title_ar'),
            'excerpt' => $request->input('excerpt'),
            'content' => $request->input('content'),
            'content_ar' => $request->input('content_ar'),
            'author_name' => $request->input('author_name'),
            'category' => $request->input('category'),
            'tags' => $request->input('tags'),
            'is_published' => $request->boolean('is_published', true),
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'updated_at' => now(),
        ];

        if ($request->hasFile('featured_image')) {
            $updateData['featured_image'] = $request->file('featured_image')->store('cms/posts', 'public');
        }

        DB::table('cms_posts')->where('id', $id)->where('business_id', $business_id)->update($updateData);

        return redirect()->route('cms.posts')->with('success', 'Post updated successfully');
    }

    public function postDelete($id)
    {
        $business_id = request()->session()->get('user.business_id');
        DB::table('cms_posts')->where('id', $id)->where('business_id', $business_id)->delete();
        return redirect()->route('cms.posts')->with('success', 'Post deleted successfully');
    }

    // ============================================================
    // BANNERS MANAGEMENT
    // ============================================================
    public function banners()
    {
        $business_id = request()->session()->get('user.business_id');
        $banners = DB::table('cms_banners')
            ->where('business_id', $business_id)
            ->orderBy('sort_order')
            ->get();

        return view('cms.banners.index', compact('banners'));
    }

    public function bannerCreate()
    {
        return view('cms.banners.create');
    }

    public function bannerStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $business_id = request()->session()->get('user.business_id');

        $image = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image')->store('cms/banners', 'public');
        }

        DB::table('cms_banners')->insert([
            'business_id' => $business_id,
            'title' => $request->input('title'),
            'title_ar' => $request->input('title_ar'),
            'subtitle' => $request->input('subtitle'),
            'subtitle_ar' => $request->input('subtitle_ar'),
            'image' => $image,
            'link' => $request->input('link'),
            'button_text' => $request->input('button_text'),
            'button_text_ar' => $request->input('button_text_ar'),
            'position' => $request->input('position', 'hero'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.banners')->with('success', 'Banner created successfully');
    }

    public function bannerEdit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $banner = DB::table('cms_banners')->where('id', $id)->where('business_id', $business_id)->first();
        if (!$banner) abort(404);
        return view('cms.banners.edit', compact('banner'));
    }

    public function bannerUpdate(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        $updateData = [
            'title' => $request->input('title'),
            'title_ar' => $request->input('title_ar'),
            'subtitle' => $request->input('subtitle'),
            'subtitle_ar' => $request->input('subtitle_ar'),
            'link' => $request->input('link'),
            'button_text' => $request->input('button_text'),
            'button_text_ar' => $request->input('button_text_ar'),
            'position' => $request->input('position', 'hero'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
            'updated_at' => now(),
        ];

        if ($request->hasFile('image')) {
            $updateData['image'] = $request->file('image')->store('cms/banners', 'public');
        }

        DB::table('cms_banners')->where('id', $id)->where('business_id', $business_id)->update($updateData);

        return redirect()->route('cms.banners')->with('success', 'Banner updated successfully');
    }

    public function bannerDelete($id)
    {
        $business_id = request()->session()->get('user.business_id');
        DB::table('cms_banners')->where('id', $id)->where('business_id', $business_id)->delete();
        return redirect()->route('cms.banners')->with('success', 'Banner deleted successfully');
    }

    // ============================================================
    // MENU MANAGEMENT
    // ============================================================
    public function menus()
    {
        $business_id = request()->session()->get('user.business_id');
        
        // Ensure main menu exists
        $mainMenu = DB::table('cms_menus')->where('business_id', $business_id)->where('name', 'main')->first();
        if (!$mainMenu) {
            DB::table('cms_menus')->insert([
                'business_id' => $business_id,
                'name' => 'main',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $mainMenu = DB::table('cms_menus')->where('business_id', $business_id)->where('name', 'main')->first();
        }

        $items = DB::table('cms_menu_items')
            ->where('menu_id', $mainMenu->id)
            ->orderBy('sort_order')
            ->get();

        return view('cms.menus.index', compact('items', 'mainMenu'));
    }

    public function menuStore(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $mainMenu = DB::table('cms_menus')->where('business_id', $business_id)->where('name', 'main')->first();

        $request->validate([
            'label' => 'required|string|max:255',
            'url' => 'required|string',
        ]);

        $maxOrder = DB::table('cms_menu_items')->where('menu_id', $mainMenu->id)->max('sort_order');

        DB::table('cms_menu_items')->insert([
            'menu_id' => $mainMenu->id,
            'label' => $request->input('label'),
            'label_ar' => $request->input('label_ar'),
            'url' => $request->input('url'),
            'type' => $request->input('type', 'custom'),
            'sort_order' => ($maxOrder ?? 0) + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.menus')->with('success', 'Menu item added successfully');
    }

    public function menuDelete($id)
    {
        DB::table('cms_menu_items')->where('id', $id)->delete();
        return redirect()->route('cms.menus')->with('success', 'Menu item removed');
    }

    public function menuReorder(Request $request)
    {
        $order = $request->input('order', []);
        foreach ($order as $index => $id) {
            DB::table('cms_menu_items')->where('id', $id)->update(['sort_order' => $index]);
        }
        return response()->json(['success' => true]);
    }

    // ============================================================
    // TESTIMONIALS
    // ============================================================
    public function testimonials()
    {
        $business_id = request()->session()->get('user.business_id');
        $testimonials = DB::table('cms_testimonials')
            ->where('business_id', $business_id)
            ->orderBy('sort_order')
            ->get();

        return view('cms.testimonials.index', compact('testimonials'));
    }

    public function testimonialStore(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $request->validate([
            'customer_name' => 'required|string',
            'review' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        DB::table('cms_testimonials')->insert([
            'business_id' => $business_id,
            'customer_name' => $request->input('customer_name'),
            'rating' => $request->input('rating', 5),
            'review' => $request->input('review'),
            'review_ar' => $request->input('review_ar'),
            'sort_order' => DB::table('cms_testimonials')->where('business_id', $business_id)->max('sort_order') + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.testimonials')->with('success', 'Testimonial added');
    }

    public function testimonialDelete($id)
    {
        $business_id = request()->session()->get('user.business_id');
        DB::table('cms_testimonials')->where('id', $id)->where('business_id', $business_id)->delete();
        return redirect()->route('cms.testimonials')->with('success', 'Testimonial removed');
    }

    // ============================================================
    // FAQs
    // ============================================================
    public function faqs()
    {
        $business_id = request()->session()->get('user.business_id');
        $faqs = DB::table('cms_faqs')
            ->where('business_id', $business_id)
            ->orderBy('sort_order')
            ->get();

        return view('cms.faqs.index', compact('faqs'));
    }

    public function faqStore(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
        ]);

        DB::table('cms_faqs')->insert([
            'business_id' => $business_id,
            'question' => $request->input('question'),
            'question_ar' => $request->input('question_ar'),
            'answer' => $request->input('answer'),
            'answer_ar' => $request->input('answer_ar'),
            'category' => $request->input('category'),
            'sort_order' => DB::table('cms_faqs')->where('business_id', $business_id)->max('sort_order') + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('cms.faqs')->with('success', 'FAQ added');
    }

    public function faqDelete($id)
    {
        $business_id = request()->session()->get('user.business_id');
        DB::table('cms_faqs')->where('id', $id)->where('business_id', $business_id)->delete();
        return redirect()->route('cms.faqs')->with('success', 'FAQ removed');
    }

    // ============================================================
    // WEBSITE PREVIEW
    // ============================================================
    public function preview()
    {
        $business_id = request()->session()->get('user.business_id');
        
        $design = DB::table('cms_design_settings')->where('business_id', $business_id)->first();
        $banners = DB::table('cms_banners')->where('business_id', $business_id)->where('is_active', true)->orderBy('sort_order')->get();
        $categories = DB::table('categories')->where('business_id', $business_id)->whereNull('parent_id')->get()->map(function ($cat) use ($business_id) {
            $cat->product_count = DB::table('products')->where('business_id', $business_id)->where('category_id', $cat->id)->where('is_inactive', 0)->count();
            $cat->image_url = $cat->image ? asset('storage/' . $cat->image) : null;
            return $cat;
        });
        $products = DB::table('products')->where('business_id', $business_id)->where('is_inactive', 0)->orderByDesc('created_at')->limit(12)->get()->map(function ($p) use ($business_id) {
            $p->image_url = $p->image ? asset('storage/' . $p->image) : null;
            $p->category_name = $p->category_id ? DB::table('categories')->where('id', $p->category_id)->value('name') : null;
            return $p;
        });
        $posts = DB::table('cms_posts')->where('business_id', $business_id)->where('is_published', true)->orderByDesc('published_at')->limit(6)->get();
        $pages = DB::table('cms_pages')->where('business_id', $business_id)->where('is_published', true)->get();
        $testimonials = DB::table('cms_testimonials')->where('business_id', $business_id)->where('is_active', true)->orderBy('sort_order')->get();
        $faqs = DB::table('cms_faqs')->where('business_id', $business_id)->where('is_active', true)->orderBy('sort_order')->get();
        
        $menuItems = collect([]);
        $mainMenu = DB::table('cms_menus')->where('business_id', $business_id)->where('name', 'main')->first();
        if ($mainMenu) {
            $menuItems = DB::table('cms_menu_items')->where('menu_id', $mainMenu->id)->where('is_active', true)->orderBy('sort_order')->get();
        }

        $business = DB::table('business')->find($business_id);

        return view('cms.preview', compact('design', 'banners', 'categories', 'products', 'posts', 'pages', 'testimonials', 'faqs', 'menuItems', 'business'));
    }

    // ============================================================
    // MEDIA LIBRARY
    // ============================================================
    public function media()
    {
        $business_id = request()->session()->get('user.business_id');
        $media = DB::table('cms_media')
            ->where('business_id', $business_id)
            ->orderByDesc('created_at')
            ->get();

        return view('cms.media.index', compact('media'));
    }

    public function mediaUpload(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('cms/media', 'public');
                DB::table('cms_media')->insert([
                    'business_id' => $business_id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'alt_text' => $request->input('alt_text'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->route('cms.media')->with('success', 'Files uploaded successfully');
    }

    public function mediaDelete($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $media = DB::table('cms_media')->where('id', $id)->where('business_id', $business_id)->first();
        if ($media && \Storage::disk('public')->exists($media->file_path)) {
            \Storage::disk('public')->delete($media->file_path);
        }
        DB::table('cms_media')->where('id', $id)->delete();
        return redirect()->route('cms.media')->with('success', 'File deleted');
    }
}
