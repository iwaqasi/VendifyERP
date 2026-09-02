<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CmsController extends BaseApiController
{
    /**
     * Get homepage data
     * GET /api/v1/cms/home
     */
    public function home(Request $request): JsonResponse
    {
        $business_id = $request->input('business_id');

        if (!$business_id) {
            return $this->errorResponse('Business ID required', 400);
        }

        // Get featured products
        $featuredProducts = DB::table('products')
            ->where('business_id', $business_id)
            ->where('is_inactive', 0)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(function ($p) {
                $p->image_url = $p->image ? asset('storage/' . $p->image) : null;
                return $p;
            });

        // Get categories with product count
        $categories = DB::table('categories')
            ->where('business_id', $business_id)
            ->whereNull('parent_id')
            ->get()
            ->map(function ($cat) use ($business_id) {
                $cat->product_count = DB::table('products')
                    ->where('business_id', $business_id)
                    ->where('category_id', $cat->id)
                    ->where('is_inactive', 0)
                    ->count();
                $cat->image_url = $cat->image ? asset('storage/' . $cat->image) : null;
                return $cat;
            });

        // Get latest blog posts
        $posts = DB::table('cms_posts')
            ->where('business_id', $business_id)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->limit(6)
            ->get()
            ->map(function ($post) {
                $post->image_url = $post->featured_image ? asset('storage/' . $post->featured_image) : null;
                return $post;
            });

        // Get homepage content
        $homepage = DB::table('cms_pages')
            ->where('business_id', $business_id)
            ->where('is_homepage', true)
            ->first();

        // Get navigation menu
        $menu = $this->getMenu($business_id, 'main');

        // Get CMS settings
        $settings = $this->getCmsSettings($business_id);

        return $this->successResponse([
            'featured_products' => $featuredProducts,
            'categories' => $categories,
            'latest_posts' => $posts,
            'homepage' => $homepage,
            'menu' => $menu,
            'settings' => $settings,
        ]);
    }

    /**
     * Get page by slug
     * GET /api/v1/cms/pages/{slug}
     */
    public function page(Request $request, string $slug): JsonResponse
    {
        $business_id = $request->input('business_id');

        $page = DB::table('cms_pages')
            ->where('business_id', $business_id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$page) {
            return $this->errorResponse('Page not found', 404);
        }

        $menu = $this->getMenu($business_id, 'main');
        $settings = $this->getCmsSettings($business_id);

        return $this->successResponse([
            'page' => $page,
            'menu' => $menu,
            'settings' => $settings,
        ]);
    }

    /**
     * Get blog posts
     * GET /api/v1/cms/posts
     */
    public function posts(Request $request): JsonResponse
    {
        $business_id = $request->input('business_id');
        
        if (!$business_id) {
            return $this->errorResponse('Business ID required', 400);
        }
        
        $category = $request->input('category');
        $search = $request->input('search');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 12);

        $query = DB::table('cms_posts')
            ->where('business_id', $business_id)
            ->where('is_published', true);

        if ($category) {
            $query->where('category', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $posts = $query->orderByDesc('published_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function ($post) {
                $post->image_url = $post->featured_image ? asset('storage/' . $post->featured_image) : null;
                return $post;
            });

        $menu = $this->getMenu($business_id, 'main');
        $settings = $this->getCmsSettings($business_id);

        return $this->successResponse([
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'menu' => $menu,
            'settings' => $settings,
        ]);
    }

    /**
     * Get single post by slug
     * GET /api/v1/cms/posts/{slug}
     */
    public function post(Request $request, string $slug): JsonResponse
    {
        $business_id = $request->input('business_id');

        $post = DB::table('cms_posts')
            ->where('business_id', $business_id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$post) {
            return $this->errorResponse('Post not found', 404);
        }

        // Increment views
        DB::table('cms_posts')
            ->where('id', $post->id)
            ->increment('views_count');

        // Get related posts
        $related = DB::table('cms_posts')
            ->where('business_id', $business_id)
            ->where('is_published', true)
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        $menu = $this->getMenu($business_id, 'main');
        $settings = $this->getCmsSettings($business_id);

        return $this->successResponse([
            'post' => $post,
            'related_posts' => $related,
            'menu' => $menu,
            'settings' => $settings,
        ]);
    }

    /**
     * Get products catalog
     * GET /api/v1/cms/products
     */
    public function products(Request $request): JsonResponse
    {
        $business_id = $request->input('business_id');
        
        if (!$business_id) {
            return $this->errorResponse('Business ID required', 400);
        }
        
        $category = $request->input('category_id');
        $search = $request->input('search');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sortBy = $request->input('sort', 'newest');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 24);

        $query = DB::table('products')
            ->where('business_id', $business_id)
            ->where('is_inactive', 0);

        if ($category) {
            $query->where('category_id', $category);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        if ($minPrice) {
            $query->where('sell_price_inc_tax', '>=', $minPrice);
        }

        if ($maxPrice) {
            $query->where('sell_price_inc_tax', '<=', $maxPrice);
        }

        // Sorting
        switch ($sortBy) {
            case 'price_low':
                $query->orderBy('sell_price_inc_tax', 'asc');
                break;
            case 'price_high':
                $query->orderBy('sell_price_inc_tax', 'desc');
                break;
            case 'name':
                $query->orderBy('name', 'asc');
                break;
            default:
                $query->orderByDesc('created_at');
        }

        $total = $query->count();
        $products = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function ($p) {
                $p->image_url = $p->image ? asset('storage/' . $p->image) : null;
                $p->category_name = DB::table('categories')
                    ->where('id', $p->category_id)
                    ->value('name');
                return $p;
            });

        $categories = DB::table('categories')
            ->where('business_id', $business_id)
            ->whereNull('parent_id')
            ->get()
            ->map(function ($cat) use ($business_id) {
                $cat->product_count = DB::table('products')
                    ->where('business_id', $business_id)
                    ->where('category_id', $cat->id)
                    ->where('is_inactive', 0)
                    ->count();
                return $cat;
            });

        $menu = $this->getMenu($business_id, 'main');
        $settings = $this->getCmsSettings($business_id);

        return $this->successResponse([
            'products' => $products,
            'categories' => $categories,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'menu' => $menu,
            'settings' => $settings,
        ]);
    }

    /**
     * Get single product
     * GET /api/v1/cms/products/{slug}
     */
    public function product(Request $request, string $slug): JsonResponse
    {
        $business_id = $request->input('business_id');

        $product = DB::table('products')
            ->where('business_id', $business_id)
            ->where('slug', $slug)
            ->where('is_inactive', 0)
            ->first();

        if (!$product) {
            return $this->errorResponse('Product not found', 404);
        }

        $product->image_url = $product->image ? asset('storage/' . $product->image) : null;
        $product->category_name = DB::table('categories')
            ->where('id', $product->category_id)
            ->value('name');

        // Get variations
        $variations = DB::table('variations')
            ->join('product_variations', 'variations.product_variation_id', '=', 'product_variations.id')
            ->where('product_variations.product_id', $product->id)
            ->get();

        // Related products
        $related = DB::table('products')
            ->where('business_id', $business_id)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_inactive', 0)
            ->limit(8)
            ->get()
            ->map(function ($p) {
                $p->image_url = $p->image ? asset('storage/' . $p->image) : null;
                return $p;
            });

        $menu = $this->getMenu($business_id, 'main');
        $settings = $this->getCmsSettings($business_id);

        return $this->successResponse([
            'product' => $product,
            'variations' => $variations,
            'related_products' => $related,
            'menu' => $menu,
            'settings' => $settings,
        ]);
    }

    /**
     * Get categories
     * GET /api/v1/cms/categories
     */
    public function categories(Request $request): JsonResponse
    {
        $business_id = $request->input('business_id');

        $categories = DB::table('categories')
            ->where('business_id', $business_id)
            ->whereNull('parent_id')
            ->get()
            ->map(function ($cat) use ($business_id) {
                $cat->product_count = DB::table('products')
                    ->where('business_id', $business_id)
                    ->where('category_id', $cat->id)
                    ->where('is_inactive', 0)
                    ->count();
                $cat->image_url = $cat->image ? asset('storage/' . $cat->image) : null;
                return $cat;
            });

        return $this->successResponse(['categories' => $categories]);
    }

    /**
     * Contact form submission
     * POST /api/v1/cms/contact
     *
     * Persists the submission as a customer/lead contact for the business
     * and stores the message in the first available custom field.
     */
    public function contact(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'message' => 'required|string|max:5000',
            'phone' => 'nullable|string|max:50',
        ]);

        $business_id = (int) $request->input('business_id');

        if (! $business_id) {
            return $this->errorResponse('Business ID required', 400);
        }

        $business = DB::table('business')->find($business_id);

        if (! $business) {
            return $this->errorResponse('Business not found', 404);
        }

        // Verify the business has the CMS enabled through its package settings.
        // Defensive: if the Superadmin module is unavailable in a non-production
        // environment we allow the submission rather than break the public form.
        try {
            $cmsEnabled = DB::table('business')
                ->join('subscriptions', 'subscriptions.business_id', '=', 'business.id')
                ->join('packages', 'packages.id', '=', 'subscriptions.package_id')
                ->where('business.id', $business_id)
                ->where('subscriptions.status', 'active')
                ->where('packages.flutter_cms', 1)
                ->exists();
        } catch (\Exception $e) {
            $cmsEnabled = config('app.env') !== 'production';
            if (! $cmsEnabled) {
                \Log::error('CMS license table unavailable in production: ' . $e->getMessage());
            }
        }

        if (! $cmsEnabled) {
            return $this->errorResponse('CMS is not enabled for this business.', 403);
        }

        // Determine a valid created_by user for this business (contacts.created_by is mandatory).
        $createdBy = DB::table('business')->where('id', $business_id)->value('owner_id')
            ?? DB::table('users')->where('business_id', $business_id)
                ->where('status', 'active')
                ->orderBy('id')
                ->value('id')
            ?? 1;

        $contactId = DB::table('contacts')->insertGetId([
            'business_id' => $business_id,
            'type' => 'customer',
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'mobile' => $request->input('phone', ''),
            'contact_status' => 'active',
            'is_default' => 0,
            'created_by' => $createdBy,
            'custom_field1' => 'Website contact form',
            'custom_field2' => $request->input('message'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Log::info('CMS contact form submission persisted', [
            'business_id' => $business_id,
            'contact_id' => $contactId,
        ]);

        return $this->successResponse(['contact_id' => $contactId], 'Message sent successfully! We will get back to you soon.', 201);
    }

    /**
     * Get CMS settings for a business
     */
    private function getCmsSettings(int $businessId): array
    {
        $settings = DB::table('cms_settings')
            ->where('business_id', $businessId)
            ->pluck('value', 'key')
            ->toArray();

        // Get business info
        $business = DB::table('business')->find($businessId);

        return [
            'business_name' => $business->name ?? '',
            'logo' => $settings['logo'] ?? null,
            'favicon' => $settings['favicon'] ?? null,
            'primary_color' => $settings['primary_color'] ?? '#00BCD4',
            'phone' => $settings['phone'] ?? '',
            'email' => $settings['email'] ?? '',
            'address' => $settings['address'] ?? '',
            'social_facebook' => $settings['social_facebook'] ?? '',
            'social_instagram' => $settings['social_instagram'] ?? '',
            'social_twitter' => $settings['social_twitter'] ?? '',
            'footer_text' => $settings['footer_text'] ?? '',
        ];
    }

    /**
     * Get menu items
     */
    private function getMenu(int $businessId, string $name): array
    {
        $menu = DB::table('cms_menus')
            ->where('business_id', $businessId)
            ->where('name', $name)
            ->first();

        if (!$menu) {
            return [];
        }

        return DB::table('cms_menu_items')
            ->where('menu_id', $menu->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }
}
