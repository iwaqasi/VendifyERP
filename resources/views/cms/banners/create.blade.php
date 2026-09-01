@extends('layouts.app')
@section('title', 'New Banner')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 style="margin:0;color:#1a237e;">🖼️ New Banner</h2>
        <a href="{{ route('cms.banners') }}" class="btn btn-outline-secondary">← Back</a>
    </div>
    <div class="row">
        <div class="col-md-8">
            <form action="{{ route('cms.banners.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:20px;">
                    <div class="card-header" style="background:#1a237e;color:white;border-radius:12px 12px 0 0;"><strong>🖼️ Banner Content</strong></div>
                    <div class="card-body">
                        <div class="form-group mb-3"><label>Title (English) <span style="color:red">*</span></label><input type="text" name="title" class="form-control" required></div>
                        <div class="form-group mb-3"><label>Title (Arabic)</label><input type="text" name="title_ar" class="form-control" dir="rtl"></div>
                        <div class="form-group mb-3"><label>Subtitle (English)</label><textarea name="subtitle" class="form-control" rows="2"></textarea></div>
                        <div class="form-group mb-3"><label>Subtitle (Arabic)</label><textarea name="subtitle_ar" class="form-control" rows="2" dir="rtl"></textarea></div>
                        <div class="form-group mb-3"><label>Banner Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                    </div>
                </div>
                <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:20px;">
                    <div class="card-header" style="background:#00BCD4;color:white;border-radius:12px 12px 0 0;"><strong>⚙️ Settings</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6"><div class="form-group mb-3"><label>Position</label><select name="position" class="form-control"><option value="hero">Hero Slider</option><option value="promo">Promo Banner</option><option value="footer">Footer</option></select></div></div>
                            <div class="col-md-6"><div class="form-group mb-3"><label>Sort Order</label><input type="number" name="sort_order" class="form-control" value="0"></div></div>
                        </div>
                        <div class="form-group mb-3"><label>Button Text</label><input type="text" name="button_text" class="form-control" placeholder="Shop Now"></div>
                        <div class="form-group mb-3"><label>Button Text (Arabic)</label><input type="text" name="button_text_ar" class="form-control" dir="rtl"></div>
                        <div class="form-group mb-3"><label>Link URL</label><input type="text" name="link" class="form-control" placeholder="/products"></div>
                        <div class="form-check mb-3"><input type="checkbox" name="is_active" class="form-check-input" value="1" checked id="active"><label class="form-check-label" for="active">Active</label></div>
                        <button type="submit" class="btn btn-success" style="width:100%;">💾 Create Banner</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-header" style="background:#4CAF50;color:white;border-radius:12px 12px 0 0;"><strong>👁️ Preview</strong></div>
                <div class="card-body"><p style="color:#888;text-align:center;padding:20px;">Upload an image and save to see the banner on the <a href="{{ route('cms.preview') }}" target="_blank">Live Preview</a></p></div>
            </div>
        </div>
    </div>
</div>
@endsection
