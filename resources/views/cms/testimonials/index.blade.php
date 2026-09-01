@extends('layouts.app')
@section('title', 'Testimonials')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 style="margin:0;color:#1a237e;">⭐ Testimonials</h2><p style="margin:4px 0 0;color:#666;">Customer reviews and ratings displayed on your website</p></div>
    </div>
    @if(session('success'))<div class="alert alert-success" style="border-radius:8px;">{{ session('success') }}</div>@endif
    <div class="row">
        <div class="col-md-8">
            <div class="row">
                @forelse($testimonials as $t)
                <div class="col-md-6 mb-4">
                    <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                        <div class="card-body">
                            <div style="margin-bottom:8px;color:#FFB300;">@for($i=0;$i<$t->rating;$i++)⭐@endfor</div>
                            <p style="font-style:italic;color:#555;">"{{ $t->review }}"</p>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="color:#1a237e;">— {{ $t->customer_name }}</strong>
                                <form action="{{ route('cms.testimonials.delete', $t->id) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">🗑️</button></form>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12" style="text-align:center;padding:40px;color:#999;">No testimonials yet.</div>
                @endforelse
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-header" style="background:#FFB300;color:white;border-radius:12px 12px 0 0;"><strong>+ Add Testimonial</strong></div>
                <div class="card-body">
                    <form action="{{ route('cms.testimonials.store') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3"><label>Customer Name</label><input type="text" name="customer_name" class="form-control" required></div>
                        <div class="form-group mb-3"><label>Rating</label><select name="rating" class="form-control"><option value="5">⭐⭐⭐⭐⭐</option><option value="4">⭐⭐⭐⭐</option><option value="3">⭐⭐⭐</option><option value="2">⭐⭐</option><option value="1">⭐</option></select></div>
                        <div class="form-group mb-3"><label>Review</label><textarea name="review" class="form-control" rows="4" required></textarea></div>
                        <div class="form-group mb-3"><label>Review (Arabic)</label><textarea name="review_ar" class="form-control" rows="3" dir="rtl"></textarea></div>
                        <button type="submit" class="btn btn-success" style="width:100%;">💾 Add Testimonial</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
