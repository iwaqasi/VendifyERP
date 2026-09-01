@extends('layouts.app')
@section('title', 'Website Designer')

@section('content')
<style>
    .designer-panel { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow: hidden; }
    .designer-header { background: linear-gradient(135deg, #1a237e, #00BCD4); color: white; padding: 20px 24px; }
    .designer-section { padding: 24px; border-bottom: 1px solid #f0f0f0; }
    .designer-section:last-child { border-bottom: none; }
    .color-picker-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .color-picker-wrap label { min-width: 160px; font-weight: 500; color: #555; }
    .color-picker-wrap input[type="color"] { width: 50px; height: 36px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; padding: 2px; }
    .color-picker-wrap .color-value { font-family: monospace; color: #888; font-size: 0.9rem; }
    .preview-frame { border: 2px solid #e0e0e0; border-radius: 12px; overflow: hidden; background: white; }
    .designer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    @media (max-width: 1200px) { .designer-grid { grid-template-columns: 1fr; } }
    .tab-buttons { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .tab-btn { padding: 8px 16px; border: 2px solid #ddd; border-radius: 8px; background: white; cursor: pointer; font-weight: 500; transition: all 0.2s; }
    .tab-btn.active { background: #1a237e; color: white; border-color: #1a237e; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .form-group label { font-weight: 500; color: #555; margin-bottom: 4px; }
    .btn-save { background: linear-gradient(135deg, #00BCD4, #1a237e); color: white; border: none; padding: 12px 32px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
    .btn-save:hover { opacity: 0.9; transform: translateY(-1px); }
    .hero-preview { background: linear-gradient(135deg, #1a237e, #00BCD4); border-radius: 12px; padding: 40px; text-align: center; color: white; margin: 16px 0; }
    .hero-preview h1 { font-size: 2rem; margin-bottom: 10px; }
    .hero-preview p { font-size: 1.1rem; opacity: 0.9; }
    .banner-card { background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 16px; }
    .banner-card img { width: 120px; height: 60px; object-fit: cover; border-radius: 6px; background: #ddd; }
    .banner-card .banner-info { flex: 1; }
</style>

<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="margin:0; color:#1a237e;">🎨 Website Designer</h2>
            <p style="margin:4px 0 0; color:#666;">Customize the look and feel of your website</p>
        </div>
        <div>
            <a href="{{ route('cms.preview') }}" class="btn btn-outline-success" target="_blank">🌐 Preview Website</a>
            <button class="btn-save" onclick="saveDesign()">💾 Save Design</button>
        </div>
    </div>

    <div class="designer-grid">
        <!-- LEFT: Settings Panel -->
        <div>
            <div class="designer-panel">
                <div class="designer-header">
                    <h4 style="margin:0;">Design Settings</h4>
                </div>

                <!-- Tab Navigation -->
                <div class="designer-section">
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="switchTab('colors')">🎨 Colors</button>
                        <button class="tab-btn" onclick="switchTab('typography')">🔤 Typography</button>
                        <button class="tab-btn" onclick="switchTab('hero')">🖼️ Hero Banner</button>
                        <button class="tab-btn" onclick="switchTab('branding')">🏷️ Branding</button>
                        <button class="tab-btn" onclick="switchTab('contact')">📞 Contact</button>
                        <button class="tab-btn" onclick="switchTab('social')">📱 Social</button>
                        <button class="tab-btn" onclick="switchTab('seo')">🔍 SEO</button>
                    </div>

                    <form id="designForm" enctype="multipart/form-data">
                        @csrf

                        <!-- COLORS TAB -->
                        <div class="tab-content active" id="tab-colors">
                            <div class="color-picker-wrap">
                                <label>Primary Color</label>
                                <input type="color" name="primary_color" value="{{ $design->primary_color ?? '#00BCD4' }}" onchange="updatePreview()">
                                <span class="color-value">{{ $design->primary_color ?? '#00BCD4' }}</span>
                            </div>
                            <div class="color-picker-wrap">
                                <label>Secondary Color</label>
                                <input type="color" name="secondary_color" value="{{ $design->secondary_color ?? '#1a237e' }}" onchange="updatePreview()">
                                <span class="color-value">{{ $design->secondary_color ?? '#1a237e' }}</span>
                            </div>
                            <div class="color-picker-wrap">
                                <label>Accent Color</label>
                                <input type="color" name="accent_color" value="{{ $design->accent_color ?? '#ff5722' }}" onchange="updatePreview()">
                                <span class="color-value">{{ $design->accent_color ?? '#ff5722' }}</span>
                            </div>
                            <div class="color-picker-wrap">
                                <label>Background Color</label>
                                <input type="color" name="background_color" value="{{ $design->background_color ?? '#ffffff' }}" onchange="updatePreview()">
                                <span class="color-value">{{ $design->background_color ?? '#ffffff' }}</span>
                            </div>
                            <div class="color-picker-wrap">
                                <label>Text Color</label>
                                <input type="color" name="text_color" value="{{ $design->text_color ?? '#333333' }}" onchange="updatePreview()">
                                <span class="color-value">{{ $design->text_color ?? '#333333' }}</span>
                            </div>
                            <div class="color-picker-wrap">
                                <label>Header Background</label>
                                <input type="color" name="header_bg_color" value="{{ $design->header_bg_color ?? '#1a237e' }}" onchange="updatePreview()">
                                <span class="color-value">{{ $design->header_bg_color ?? '#1a237e' }}</span>
                            </div>
                            <div class="color-picker-wrap">
                                <label>Footer Background</label>
                                <input type="color" name="footer_bg_color" value="{{ $design->footer_bg_color ?? '#1a237e' }}" onchange="updatePreview()">
                                <span class="color-value">{{ $design->footer_bg_color ?? '#1a237e' }}</span>
                            </div>
                        </div>

                        <!-- TYPOGRAPHY TAB -->
                        <div class="tab-content" id="tab-typography">
                            <div class="form-group mb-3">
                                <label>Font Family</label>
                                <select name="font_family" class="form-control" onchange="updatePreview()">
                                    <option value="Inter" {{ ($design->font_family ?? '') == 'Inter' ? 'selected' : '' }}>Inter</option>
                                    <option value="Roboto" {{ ($design->font_family ?? '') == 'Roboto' ? 'selected' : '' }}>Roboto</option>
                                    <option value="Open Sans" {{ ($design->font_family ?? '') == 'Open Sans' ? 'selected' : '' }}>Open Sans</option>
                                    <option value="Lato" {{ ($design->font_family ?? '') == 'Lato' ? 'selected' : '' }}>Lato</option>
                                    <option value="Poppins" {{ ($design->font_family ?? '') == 'Poppins' ? 'selected' : '' }}>Poppins</option>
                                    <option value="Montserrat" {{ ($design->font_family ?? '') == 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                                    <option value="Playfair Display" {{ ($design->font_family ?? '') == 'Playfair Display' ? 'selected' : '' }}>Playfair Display</option>
                                    <option value="Cairo" {{ ($design->font_family ?? '') == 'Cairo' ? 'selected' : '' }}>Cairo (Arabic)</option>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label>Heading Font</label>
                                <select name="heading_font" class="form-control">
                                    <option value="Inter" {{ ($design->heading_font ?? '') == 'Inter' ? 'selected' : '' }}>Inter</option>
                                    <option value="Roboto" {{ ($design->heading_font ?? '') == 'Roboto' ? 'selected' : '' }}>Roboto</option>
                                    <option value="Montserrat" {{ ($design->heading_font ?? '') == 'Montserrat' ? 'selected' : '' }}>Montserrat</option>
                                    <option value="Playfair Display" {{ ($design->heading_font ?? '') == 'Playfair Display' ? 'selected' : '' }}>Playfair Display</option>
                                </select>
                            </div>
                        </div>

                        <!-- HERO BANNER TAB -->
                        <div class="tab-content" id="tab-hero">
                            <div class="form-group mb-3">
                                <label>Hero Title (English)</label>
                                <input type="text" name="hero_title" class="form-control" value="{{ $design->hero_title ?? '' }}" placeholder="Welcome to Our Store">
                            </div>
                            <div class="form-group mb-3">
                                <label>Hero Title (Arabic)</label>
                                <input type="text" name="hero_title_ar" class="form-control" value="{{ $design->hero_title_ar ?? '' }}" placeholder="مرحبا بكم في متجرنا" dir="rtl">
                            </div>
                            <div class="form-group mb-3">
                                <label>Hero Subtitle (English)</label>
                                <textarea name="hero_subtitle" class="form-control" rows="2" placeholder="Discover our amazing collection">{{ $design->hero_subtitle ?? '' }}</textarea>
                            </div>
                            <div class="form-group mb-3">
                                <label>Hero Subtitle (Arabic)</label>
                                <textarea name="hero_subtitle_ar" class="form-control" rows="2" dir="rtl">{{ $design->hero_subtitle_ar ?? '' }}</textarea>
                            </div>
                            <div class="form-group mb-3">
                                <label>CTA Button Text</label>
                                <input type="text" name="hero_cta_text" class="form-control" value="{{ $design->hero_cta_text ?? 'Shop Now' }}" placeholder="Shop Now">
                            </div>
                            <div class="form-group mb-3">
                                <label>CTA Button Link</label>
                                <input type="text" name="hero_cta_link" class="form-control" value="{{ $design->hero_cta_link ?? '/products' }}" placeholder="/products">
                            </div>
                            <div class="form-group mb-3">
                                <label>Hero Background Image</label>
                                <input type="file" name="hero_image" class="form-control" accept="image/*">
                                @if(!empty($design->hero_image))
                                    <img src="{{ asset('storage/' . $design->hero_image) }}" style="margin-top:10px; max-width:200px; border-radius:8px;">
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <label>Footer Text</label>
                                <textarea name="footer_text" class="form-control" rows="2">{{ $design->footer_text ?? '' }}</textarea>
                            </div>

                            <!-- Hero Preview -->
                            <div class="hero-preview" id="heroPreview">
                                <h1>{{ $design->hero_title ?? 'Welcome to Our Store' }}</h1>
                                <p>{{ $design->hero_subtitle ?? 'Discover our amazing collection' }}</p>
                                <a href="#" style="display: inline-block; margin-top: 16px; padding: 12px 32px; background: {{ $design->accent_color ?? '#ff5722' }}; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                                    {{ $design->hero_cta_text ?? 'Shop Now' }}
                                </a>
                            </div>
                        </div>

                        <!-- BRANDING TAB -->
                        <div class="tab-content" id="tab-branding">
                            <div class="form-group mb-3">
                                <label>Logo</label>
                                <input type="file" name="logo" class="form-control" accept="image/*">
                                @if(!empty($design->logo))
                                    <img src="{{ asset('storage/' . $design->logo) }}" style="margin-top:10px; max-height:60px;">
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <label>Favicon</label>
                                <input type="file" name="favicon" class="form-control" accept="image/*">
                            </div>
                        </div>

                        <!-- CONTACT TAB -->
                        <div class="tab-content" id="tab-contact">
                            <div class="form-group mb-3">
                                <label>Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="{{ $design->phone ?? '' }}">
                            </div>
                            <div class="form-group mb-3">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" value="{{ $design->email ?? '' }}">
                            </div>
                            <div class="form-group mb-3">
                                <label>Address</label>
                                <textarea name="address" class="form-control" rows="3">{{ $design->address ?? '' }}</textarea>
                            </div>
                            <div class="form-group mb-3">
                                <label>WhatsApp Number</label>
                                <input type="text" name="whatsapp_number" class="form-control" value="{{ $design->whatsapp_number ?? '' }}" placeholder="+965XXXXXXXX">
                            </div>
                        </div>

                        <!-- SOCIAL TAB -->
                        <div class="tab-content" id="tab-social">
                            <div class="form-group mb-3">
                                <label>Facebook URL</label>
                                <input type="url" name="facebook_url" class="form-control" value="{{ $design->facebook_url ?? '' }}" placeholder="https://facebook.com/...">
                            </div>
                            <div class="form-group mb-3">
                                <label>Instagram URL</label>
                                <input type="url" name="instagram_url" class="form-control" value="{{ $design->instagram_url ?? '' }}" placeholder="https://instagram.com/...">
                            </div>
                            <div class="form-group mb-3">
                                <label>Twitter URL</label>
                                <input type="url" name="twitter_url" class="form-control" value="{{ $design->twitter_url ?? '' }}" placeholder="https://twitter.com/...">
                            </div>
                        </div>

                        <!-- SEO TAB -->
                        <div class="tab-content" id="tab-seo">
                            <div class="form-group mb-3">
                                <label>Meta Title</label>
                                <input type="text" name="seo_title" class="form-control" value="{{ $design->seo_settings['meta_title'] ?? '' }}">
                            </div>
                            <div class="form-group mb-3">
                                <label>Meta Description</label>
                                <textarea name="seo_description" class="form-control" rows="3">{{ $design->seo_settings['meta_description'] ?? '' }}</textarea>
                            </div>
                            <div class="form-group mb-3">
                                <label>Custom CSS</label>
                                <textarea name="custom_css" class="form-control" rows="4" style="font-family: monospace;" placeholder=".custom-class { color: red; }">{{ $design->custom_css ?? '' }}</textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- BANNERS SECTION -->
            <div class="designer-panel mt-4">
                <div class="designer-header" style="background: linear-gradient(135deg, #FF9800, #ff5722);">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 style="margin:0;">🖼️ Banners / Sliders</h4>
                        <a href="{{ route('cms.banners.create') }}" class="btn btn-light btn-sm">+ Add Banner</a>
                    </div>
                </div>
                <div class="designer-section">
                    @if($banners->count())
                        @foreach($banners as $banner)
                        <div class="banner-card">
                            @if($banner->image)
                                <img src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}">
                            @else
                                <div style="width:120px; height:60px; background: linear-gradient(135deg, {{ $design->primary_color ?? '#00BCD4' }}, {{ $design->secondary_color ?? '#1a237e' }}); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">🖼️</div>
                            @endif
                            <div class="banner-info">
                                <strong>{{ $banner->title }}</strong>
                                <br><small style="color: #888;">{{ ucfirst($banner->position) }} • Sort: {{ $banner->sort_order }} • {{ $banner->is_active ? '✅ Active' : '❌ Inactive' }}</small>
                            </div>
                            <a href="{{ route('cms.banners.edit', $banner->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('cms.banners.delete', $banner->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this banner?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">🗑️</button>
                            </form>
                        </div>
                        @endforeach
                    @else
                        <div style="text-align: center; padding: 30px; color: #999;">
                            No banners yet. <a href="{{ route('cms.banners.create') }}">Add your first banner</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- RIGHT: Live Preview -->
        <div>
            <div class="designer-panel" style="position: sticky; top: 20px;">
                <div class="designer-header" style="background: linear-gradient(135deg, #4CAF50, #2E7D32);">
                    <h4 style="margin:0;">🌐 Live Preview</h4>
                </div>
                <div style="padding: 16px;">
                    <div class="preview-frame" id="previewFrame">
                        <!-- Mini preview of the website -->
                        <div id="miniPreview" style="background: white; min-height: 600px;">
                            <!-- Header -->
                            <div id="previewHeader" style="background: {{ $design->header_bg_color ?? '#1a237e' }}; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="color: white; font-weight: bold; font-size: 1.1rem;">{{ $business->name ?? 'My Store' }}</div>
                                <div style="display: flex; gap: 16px;">
                                    <span style="color: white; font-size: 0.8rem;">Home</span>
                                    <span style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">Products</span>
                                    <span style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">Blog</span>
                                    <span style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">Contact</span>
                                </div>
                            </div>

                            <!-- Hero -->
                            <div id="previewHero" style="background: linear-gradient(135deg, {{ $design->primary_color ?? '#00BCD4' }}, {{ $design->secondary_color ?? '#1a237e' }}); padding: 40px 20px; text-align: center; color: white;">
                                <h2 id="previewHeroTitle" style="margin: 0 0 8px; font-size: 1.5rem;">{{ $design->hero_title ?? 'Welcome to Our Store' }}</h2>
                                <p id="previewHeroSubtitle" style="margin: 0 0 16px; opacity: 0.9;">{{ $design->hero_subtitle ?? 'Discover our amazing collection' }}</p>
                                <span id="previewHeroCta" style="display: inline-block; padding: 8px 24px; background: {{ $design->accent_color ?? '#ff5722' }}; color: white; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">{{ $design->hero_cta_text ?? 'Shop Now' }}</span>
                            </div>

                            <!-- Categories -->
                            <div style="padding: 20px;">
                                <h4 style="color: {{ $design->secondary_color ?? '#1a237e' }}; margin-bottom: 12px;">Shop by Category</h4>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
                                    <div style="background: #f5f5f5; border-radius: 8px; padding: 12px; text-align: center; font-size: 0.8rem;">
                                        <div style="font-size: 1.5rem;">👗</div>
                                        Fashion
                                    </div>
                                    <div style="background: #f5f5f5; border-radius: 8px; padding: 12px; text-align: center; font-size: 0.8rem;">
                                        <div style="font-size: 1.5rem;">💍</div>
                                        Jewelry
                                    </div>
                                    <div style="background: #f5f5f5; border-radius: 8px; padding: 12px; text-align: center; font-size: 0.8rem;">
                                        <div style="font-size: 1.5rem;">👜</div>
                                        Accessories
                                    </div>
                                </div>
                            </div>

                            <!-- Featured Products -->
                            <div style="padding: 0 20px 20px;">
                                <h4 style="color: {{ $design->secondary_color ?? '#1a237e' }}; margin-bottom: 12px;">Featured Products</h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                                    <div style="background: #f9f9f9; border-radius: 8px; padding: 8px; text-align: center;">
                                        <div style="width: 100%; height: 60px; background: #e0e0e0; border-radius: 6px; margin-bottom: 6px;"></div>
                                        <div style="font-size: 0.75rem; font-weight: 600;">Product Name</div>
                                        <div style="font-size: 0.8rem; color: {{ $design->primary_color ?? '#00BCD4' }}; font-weight: bold;">KD 25.000</div>
                                    </div>
                                    <div style="background: #f9f9f9; border-radius: 8px; padding: 8px; text-align: center;">
                                        <div style="width: 100%; height: 60px; background: #e0e0e0; border-radius: 6px; margin-bottom: 6px;"></div>
                                        <div style="font-size: 0.75rem; font-weight: 600;">Product Name</div>
                                        <div style="font-size: 0.8rem; color: {{ $design->primary_color ?? '#00BCD4' }}; font-weight: bold;">KD 18.500</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div id="previewFooter" style="background: {{ $design->footer_bg_color ?? '#1a237e' }}; color: white; padding: 20px; text-align: center;">
                                <div style="font-weight: bold; margin-bottom: 8px;">{{ $business->name ?? 'My Store' }}</div>
                                <div style="font-size: 0.8rem; opacity: 0.7;">{{ $design->footer_text ?? '© 2026 All rights reserved' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

function updatePreview() {
    const form = document.getElementById('designForm');
    const data = new FormData(form);
    
    // Update colors in mini preview
    document.getElementById('previewHeader').style.background = data.get('header_bg_color');
    document.getElementById('previewHero').style.background = 'linear-gradient(135deg, ' + data.get('primary_color') + ', ' + data.get('secondary_color') + ')';
    document.getElementById('previewFooter').style.background = data.get('footer_bg_color');
    
    // Update color value displays
    document.querySelectorAll('input[type="color"]').forEach(input => {
        const span = input.nextElementSibling;
        if (span) span.textContent = input.value;
    });
}

function saveDesign() {
    const form = document.getElementById('designForm');
    const formData = new FormData(form);
    
    // Collect SEO settings
    formData.set('seo_settings[meta_title]', form.querySelector('[name="seo_title"]')?.value || '');
    formData.set('seo_settings[meta_description]', form.querySelector('[name="seo_description"]')?.value || '');
    
    fetch('{{ route("cms.designer.save") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Show success toast
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:20px;right:20px;background:#4CAF50;color:white;padding:16px 24px;border-radius:8px;z-index:9999;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.2);';
            toast.textContent = '✅ Design saved successfully!';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        } else {
            alert('Error: ' + (data.message || 'Failed to save'));
        }
    })
    .catch(err => {
        alert('Error saving design: ' + err.message);
    });
}
</script>
@endsection
