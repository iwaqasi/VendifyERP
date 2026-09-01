<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $design->seo_settings['meta_title'] ?? ($business->name ?? 'My Website') }}</title>
    <meta name="description" content="{{ $design->seo_settings['meta_description'] ?? '' }}">
    <link href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $design->font_family ?? 'Inter') }}:wght@300;400;500;600;700&family={{ str_replace(' ', '+', $design->heading_font ?? 'Inter') }}:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: '{{ $design->font_family ?? 'Inter' }}', sans-serif; color: {{ $design->text_color ?? '#333' }}; background: {{ $design->background_color ?? '#fff' }}; }
        h1,h2,h3,h4,h5,h6 { font-family: '{{ $design->heading_font ?? 'Inter' }}', sans-serif; }

        /* HEADER */
        .site-header { background: {{ $design->header_bg_color ?? '#1a237e' }}; padding: 0; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-top { padding: 8px 40px; font-size: 0.8rem; color: rgba(255,255,255,0.7); display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .header-main { padding: 12px 40px; display: flex; justify-content: space-between; align-items: center; }
        .site-logo { color: white; font-size: 1.5rem; font-weight: 700; text-decoration: none; }
        .site-logo img { height: 40px; margin-right: 10px; }
        .nav-links { display: flex; gap: 24px; }
        .nav-links a { color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 500; font-size: 0.95rem; transition: color 0.2s; }
        .nav-links a:hover, .nav-links a.active { color: {{ $design->accent_color ?? '#ff5722' }}; }
        .header-actions { display: flex; gap: 16px; align-items: center; }
        .header-actions a { color: white; font-size: 1.1rem; text-decoration: none; }

        /* HERO */
        .hero { background: linear-gradient(135deg, {{ $design->primary_color ?? '#00BCD4' }}, {{ $design->secondary_color ?? '#1a237e' }}); padding: 80px 40px; text-align: center; color: white; position: relative; overflow: hidden; }
        @if($design->hero_image)
        .hero { background: url('{{ asset("storage/" . $design->hero_image) }}') center/cover; }
        .hero::before { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,0.4); }
        @endif
        .hero * { position: relative; z-index: 1; }
        .hero h1 { font-size: 3rem; font-weight: 700; margin-bottom: 16px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .hero p { font-size: 1.2rem; opacity: 0.9; margin-bottom: 32px; max-width: 600px; margin-left: auto; margin-right: auto; }
        .hero-cta { display: inline-block; padding: 14px 40px; background: {{ $design->accent_color ?? '#ff5722' }}; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 1.1rem; transition: transform 0.2s, box-shadow 0.2s; }
        .hero-cta:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }

        /* SECTIONS */
        .section { padding: 60px 40px; max-width: 1200px; margin: 0 auto; }
        .section-title { text-align: center; margin-bottom: 40px; }
        .section-title h2 { font-size: 2rem; color: {{ $design->secondary_color ?? '#1a237e' }}; margin-bottom: 8px; }
        .section-title p { color: #888; font-size: 1rem; }

        /* CATEGORIES GRID */
        .categories-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .category-card { background: white; border-radius: 12px; padding: 30px 20px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; border: 2px solid transparent; }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: {{ $design->primary_color ?? '#00BCD4' }}; }
        .category-card img { width: 80px; height: 80px; object-fit: cover; border-radius: 50%; margin-bottom: 12px; }
        .category-card .cat-icon { font-size: 2.5rem; margin-bottom: 12px; }
        .category-card h4 { margin: 0 0 4px; color: {{ $design->secondary_color ?? '#1a237e' }}; font-size: 1rem; }
        .category-card span { color: #999; font-size: 0.85rem; }

        /* PRODUCTS GRID */
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 24px; }
        .product-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .product-card .product-img { width: 100%; height: 250px; object-fit: cover; background: #f0f0f0; }
        .product-card .product-info { padding: 16px; }
        .product-card h4 { margin: 0 0 8px; font-size: 1rem; color: #333; }
        .product-card .product-price { font-size: 1.2rem; font-weight: 700; color: {{ $design->primary_color ?? '#00BCD4' }}; }
        .product-card .product-category { font-size: 0.8rem; color: #999; margin-top: 4px; }

        /* BANNERS */
        .banner-slider { position: relative; overflow: hidden; }
        .banner-slide { display: none; text-align: center; padding: 60px 40px; color: white; background: linear-gradient(135deg, {{ $design->primary_color ?? '#00BCD4' }}, {{ $design->secondary_color ?? '#1a237e' }}); }
        .banner-slide.active { display: block; }
        .banner-slide img { max-width: 100%; max-height: 400px; border-radius: 12px; margin-bottom: 20px; }
        .banner-slide h2 { font-size: 2rem; margin-bottom: 8px; }
        .banner-slide p { font-size: 1.1rem; opacity: 0.9; margin-bottom: 20px; }

        /* TESTIMONIALS */
        .testimonials-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }
        .testimonial-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .testimonial-card .stars { color: #FFB300; font-size: 1.2rem; margin-bottom: 12px; }
        .testimonial-card .review { font-style: italic; color: #555; margin-bottom: 16px; line-height: 1.6; }
        .testimonial-card .customer { font-weight: 600; color: {{ $design->secondary_color ?? '#1a237e' }}; }

        /* FAQ */
        .faq-item { background: white; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; }
        .faq-question { padding: 16px 20px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: {{ $design->secondary_color ?? '#1a237e' }}; }
        .faq-answer { padding: 0 20px 16px; color: #555; line-height: 1.6; display: none; }
        .faq-item.open .faq-answer { display: block; }
        .faq-item.open .faq-question i { transform: rotate(180deg); }
        .faq-question i { transition: transform 0.2s; }

        /* BLOG */
        .posts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; }
        .post-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .post-card img { width: 100%; height: 180px; object-fit: cover; }
        .post-card .post-body { padding: 20px; }
        .post-card h3 { margin: 0 0 8px; font-size: 1.1rem; }
        .post-card .post-meta { font-size: 0.85rem; color: #999; margin-bottom: 12px; }
        .post-card .post-excerpt { color: #666; font-size: 0.95rem; line-height: 1.5; }

        /* NEWSLETTER */
        .newsletter { background: linear-gradient(135deg, {{ $design->primary_color ?? '#00BCD4' }}, {{ $design->secondary_color ?? '#1a237e' }}); padding: 50px 40px; text-align: center; color: white; }
        .newsletter input { padding: 12px 20px; border: none; border-radius: 8px; width: 300px; font-size: 1rem; margin-right: 8px; }
        .newsletter button { padding: 12px 32px; background: {{ $design->accent_color ?? '#ff5722' }}; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; }

        /* FOOTER */
        .site-footer { background: {{ $design->footer_bg_color ?? '#1a237e' }}; color: white; padding: 50px 40px 20px; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; max-width: 1200px; margin: 0 auto; }
        .footer-brand h3 { margin-bottom: 12px; font-size: 1.3rem; }
        .footer-brand p { opacity: 0.7; line-height: 1.6; font-size: 0.9rem; }
        .footer-col h4 { margin-bottom: 16px; font-size: 1rem; }
        .footer-col a { display: block; color: rgba(255,255,255,0.7); text-decoration: none; margin-bottom: 8px; font-size: 0.9rem; transition: color 0.2s; }
        .footer-col a:hover { color: {{ $design->accent_color ?? '#ff5722' }}; }
        .footer-social { display: flex; gap: 16px; margin-top: 16px; }
        .footer-social a { color: white; font-size: 1.2rem; }
        .footer-bottom { text-align: center; padding-top: 20px; margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem; opacity: 0.6; }

        /* PREVIEW BAR */
        .preview-bar { position: fixed; top: 0; left: 0; right: 0; background: #333; color: white; padding: 8px 20px; z-index: 9999; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; }
        .preview-bar a { color: #4FC3F7; text-decoration: none; }
        .site-header { margin-top: 36px; }
    </style>
    @if($design->custom_css)
    <style>{!! $design->custom_css !!}</style>
    @endif
</head>
<body>

<!-- PREVIEW BAR -->
<div class="preview-bar">
    <span>🌐 <strong>Website Preview</strong> — {{ $business->name ?? 'My Website' }}</span>
    <div>
        <a href="{{ route('cms.designer') }}">← Back to Designer</a> &nbsp;|&nbsp;
        <a href="{{ route('cms.dashboard') }}">CMS Dashboard</a>
    </div>
</div>

<!-- HEADER -->
<header class="site-header">
    <div class="header-top">
        <div>
            @if($design->phone) <span>📞 {{ $design->phone }}</span> &nbsp;&nbsp; @endif
            @if($design->email) <span>✉️ {{ $design->email }}</span> @endif
        </div>
        <div>
            @if($design->facebook_url) <a href="{{ $design->facebook_url }}" style="color:white;margin:0 6px;"><i class="fab fa-facebook"></i></a> @endif
            @if($design->instagram_url) <a href="{{ $design->instagram_url }}" style="color:white;margin:0 6px;"><i class="fab fa-instagram"></i></a> @endif
            @if($design->twitter_url) <a href="{{ $design->twitter_url }}" style="color:white;margin:0 6px;"><i class="fab fa-twitter"></i></a> @endif
        </div>
    </div>
    <div class="header-main">
        <a href="#" class="site-logo">
            @if($design->logo) <img src="{{ asset('storage/' . $design->logo) }}" alt="Logo"> @endif
            {{ $business->name ?? 'My Store' }}
        </a>
        <nav class="nav-links">
            <a href="#" class="active">Home</a>
            <a href="#products">Products</a>
            @if($menuItems->count())
                @foreach($menuItems as $item)
                    <a href="{{ $item->url }}">{{ $item->label }}</a>
                @endforeach
            @endif
            <a href="#blog">Blog</a>
            <a href="#contact">Contact</a>
        </nav>
        <div class="header-actions">
            <a href="#"><i class="fas fa-search"></i></a>
            <a href="#"><i class="fas fa-shopping-cart"></i></a>
            <a href="#"><i class="fas fa-user"></i></a>
        </div>
    </div>
</header>

<!-- HERO / BANNERS -->
@if($banners->where('position', 'hero')->count())
    <div class="banner-slider" id="heroSlider">
        @foreach($banners->where('position', 'hero') as $index => $banner)
        <div class="banner-slide {{ $index == 0 ? 'active' : '' }}" style="background: {{ $banner->image ? 'url(' . asset('storage/' . $banner->image) . ') center/cover' : 'linear-gradient(135deg, ' . ($design->primary_color ?? '#00BCD4') . ', ' . ($design->secondary_color ?? '#1a237e') . ')' }};">
            @if(!$banner->image)<div style="position:absolute;inset:0;background:rgba(0,0,0,0.3);"></div>@endif
            <div style="position:relative;z-index:1;">
                <h2>{{ $banner->title }}</h2>
                @if($banner->subtitle)<p>{{ $banner->subtitle }}</p>@endif
                @if($banner->button_text)<a href="{{ $banner->link ?? '#' }}" class="hero-cta">{{ $banner->button_text }}</a>@endif
            </div>
        </div>
        @endforeach
    </div>
@else
    <section class="hero">
        <h1>{{ $design->hero_title ?? 'Welcome to ' . ($business->name ?? 'Our Store') }}</h1>
        <p>{{ $design->hero_subtitle ?? 'Discover our amazing collection of products' }}</p>
        <a href="{{ $design->hero_cta_link ?? '#products' }}" class="hero-cta">{{ $design->hero_cta_text ?? 'Shop Now' }}</a>
    </section>
@endif

<!-- PROMO BANNERS -->
@if($banners->where('position', 'promo')->count())
<div class="section" style="padding-bottom: 20px;">
    <div style="display: grid; grid-template-columns: repeat({{ min($banners->where('position', 'promo')->count(), 3) }}, 1fr); gap: 20px;">
        @foreach($banners->where('position', 'promo') as $promo)
        <div style="border-radius: 12px; overflow: hidden; {{ $promo->image ? 'background: url(' . asset('storage/' . $promo->image) . ') center/cover; min-height: 150px;' : 'background: linear-gradient(135deg, ' . ($design->primary_color ?? '#00BCD4') . ', ' . ($design->secondary_color ?? '#1a237e') . '); padding: 30px; color: white; text-align: center;' }}">
            @if(!$promo->image)
                <h3>{{ $promo->title }}</h3>
                @if($promo->button_text)<a href="{{ $promo->link ?? '#' }}" style="display:inline-block;margin-top:12px;padding:8px 20px;background:{{ $design->accent_color ?? '#ff5722' }};color:white;border-radius:6px;text-decoration:none;">{{ $promo->button_text }}</a>@endif
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

<!-- CATEGORIES -->
@if($categories->count())
<section class="section">
    <div class="section-title">
        <h2>Shop by Category</h2>
        <p>Browse our collections</p>
    </div>
    <div class="categories-grid">
        @foreach($categories as $cat)
        <div class="category-card">
            @if($cat->image_url)
                <img src="{{ $cat->image_url }}" alt="{{ $cat->name }}">
            @else
                <div class="cat-icon">📦</div>
            @endif
            <h4>{{ $cat->name }}</h4>
            <span>{{ $cat->product_count }} products</span>
        </div>
        @endforeach
    </div>
</section>
@endif

<!-- PRODUCTS -->
<section class="section" id="products" style="background: #f8f9fa; max-width: 100%; padding: 60px 40px;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div class="section-title">
            <h2>Featured Products</h2>
            <p>Our latest arrivals</p>
        </div>
        <div class="products-grid">
            @forelse($products as $product)
            <div class="product-card">
                @if($product->image_url)
                    <img src="{{ $product->image_url }}" class="product-img" alt="{{ $product->name }}">
                @else
                    <div class="product-img" style="display:flex;align-items:center;justify-content:center;font-size:3rem;color:#ccc;">📦</div>
                @endif
                <div class="product-info">
                    <h4>{{ $product->name }}</h4>
                    <div class="product-price">KD {{ number_format($product->default_sell_price_inc_tax ?? $product->sell_price_inc_tax ?? 0, 3) }}</div>
                    @if(!empty($product->category_name))<div class="product-category">{{ $product->category_name }}</div>@endif
                </div>
            </div>
            @empty
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #999;">
                No products yet. Add products from the admin panel.
            </div>
            @endforelse
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
@if($testimonials->count())
<section class="section">
    <div class="section-title">
        <h2>What Our Customers Say</h2>
        <p>Real reviews from happy customers</p>
    </div>
    <div class="testimonials-grid">
        @foreach($testimonials as $t)
        <div class="testimonial-card">
            <div class="stars">@for($i=0;$i<$t->rating;$i++)⭐@endfor</div>
            <p class="review">"{{ $t->review }}"</p>
            <div class="customer">— {{ $t->customer_name }}</div>
        </div>
        @endforeach
    </div>
</section>
@endif

<!-- BLOG -->
@if($posts->count())
<section class="section" id="blog" style="background: #f8f9fa; max-width: 100%; padding: 60px 40px;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div class="section-title">
            <h2>Latest from Our Blog</h2>
            <p>News, tips, and updates</p>
        </div>
        <div class="posts-grid">
            @foreach($posts as $post)
            <div class="post-card">
                @if($post->featured_image)
                    <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}">
                @else
                    <div style="height:180px;background:linear-gradient(135deg,{{ $design->primary_color ?? '#00BCD4' }},{{ $design->secondary_color ?? '#1a237e' }});display:flex;align-items:center;justify-content:center;color:white;font-size:2rem;">📝</div>
                @endif
                <div class="post-body">
                    <div class="post-meta">
                        @if($post->category)<span class="badge" style="background:{{ $design->primary_color ?? '#00BCD4' }};color:white;padding:2px 8px;border-radius:4px;font-size:0.75rem;">{{ $post->category }}</span> @endif
                        {{ $post->published_at ? date('M d, Y', strtotime($post->published_at)) : '' }} • {{ $post->author_name ?? 'Admin' }}
                    </div>
                    <h3>{{ $post->title }}</h3>
                    <p class="post-excerpt">{{ $post->excerpt ?? substr(strip_tags($post->content), 0, 120) . '...' }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- FAQ -->
@if($faqs->count())
<section class="section" id="faq">
    <div class="section-title">
        <h2>Frequently Asked Questions</h2>
    </div>
    <div style="max-width: 800px; margin: 0 auto;">
        @foreach($faqs as $faq)
        <div class="faq-item" onclick="this.classList.toggle('open')">
            <div class="faq-question">{{ $faq->question }} <i class="fas fa-chevron-down"></i></div>
            <div class="faq-answer">{{ $faq->answer }}</div>
        </div>
        @endforeach
    </div>
</section>
@endif

<!-- NEWSLETTER -->
<section class="newsletter">
    <h2 style="margin-bottom: 8px; font-size: 1.8rem;">Stay Updated</h2>
    <p style="margin-bottom: 24px; opacity: 0.9;">Subscribe to our newsletter for the latest products and offers</p>
    <div>
        <input type="email" placeholder="Enter your email address">
        <button>Subscribe</button>
    </div>
</section>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="footer-grid">
        <div class="footer-brand">
            <h3>{{ $business->name ?? 'My Store' }}</h3>
            <p>{{ $design->address ?? '' }}</p>
            @if($design->whatsapp_number)<p style="margin-top:8px;">📱 WhatsApp: {{ $design->whatsapp_number }}</p>@endif
            <div class="footer-social">
                @if($design->facebook_url)<a href="{{ $design->facebook_url }}"><i class="fab fa-facebook-f"></i></a>@endif
                @if($design->instagram_url)<a href="{{ $design->instagram_url }}"><i class="fab fa-instagram"></i></a>@endif
                @if($design->twitter_url)<a href="{{ $design->twitter_url }}"><i class="fab fa-twitter"></i></a>@endif
            </div>
        </div>
        <div class="footer-col">
            <h4>Quick Links</h4>
            <a href="#">Home</a>
            <a href="#products">Products</a>
            <a href="#blog">Blog</a>
            <a href="#contact">Contact</a>
        </div>
        <div class="footer-col">
            <h4>Categories</h4>
            @foreach($categories->take(5) as $cat)
                <a href="#">{{ $cat->name }}</a>
            @endforeach
        </div>
        <div class="footer-col">
            <h4>Contact</h4>
            @if($design->phone)<a href="tel:{{ $design->phone }}">📞 {{ $design->phone }}</a>@endif
            @if($design->email)<a href="mailto:{{ $design->email }}">✉️ {{ $design->email }}</a>@endif
            @if($design->address)<a href="#">📍 {{ $design->address }}</a>@endif
        </div>
    </div>
    <div class="footer-bottom">
        {{ $design->footer_text ?? '© ' . date('Y') . ' ' . ($business->name ?? 'My Store') . '. All rights reserved.' }}
    </div>
</footer>

<script>
// Banner slider
let currentSlide = 0;
const slides = document.querySelectorAll('.banner-slide');
if (slides.length > 1) {
    setInterval(() => {
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');
    }, 5000);
}
</script>
</body>
</html>
