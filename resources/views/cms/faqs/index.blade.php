@extends('layouts.app')
@section('title', 'FAQs')
@section('content')
<div class="container-fluid" style="padding: 20px 30px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h2 style="margin:0;color:#1a237e;">❓ Frequently Asked Questions</h2><p style="margin:4px 0 0;color:#666;">Common questions from your customers</p></div>
    </div>
    @if(session('success'))<div class="alert alert-success" style="border-radius:8px;">{{ session('success') }}</div>@endif
    <div class="row">
        <div class="col-md-8">
            @forelse($faqs as $faq)
            <div class="card mb-3" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="flex:1;">
                            <h5 style="color:#1a237e;margin:0 0 8px;">Q: {{ $faq->question }}</h5>
                            <p style="color:#555;margin:0;">A: {{ $faq->answer }}</p>
                            @if($faq->category)<span class="badge badge-info" style="margin-top:8px;">{{ $faq->category }}</span>@endif
                        </div>
                        <form action="{{ route('cms.faqs.delete', $faq->id) }}" method="POST" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">🗑️</button></form>
                    </div>
                </div>
            </div>
            @empty
            <div style="text-align:center;padding:60px;color:#999;">No FAQs yet. Add one below.</div>
            @endforelse
        </div>
        <div class="col-md-4">
            <div class="card" style="border-radius:12px;border:none;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                <div class="card-header" style="background:#FF5722;color:white;border-radius:12px 12px 0 0;"><strong>+ Add FAQ</strong></div>
                <div class="card-body">
                    <form action="{{ route('cms.faqs.store') }}" method="POST">
                        @csrf
                        <div class="form-group mb-3"><label>Question (English)</label><input type="text" name="question" class="form-control" required></div>
                        <div class="form-group mb-3"><label>Question (Arabic)</label><input type="text" name="question_ar" class="form-control" dir="rtl"></div>
                        <div class="form-group mb-3"><label>Answer (English)</label><textarea name="answer" class="form-control" rows="4" required></textarea></div>
                        <div class="form-group mb-3"><label>Answer (Arabic)</label><textarea name="answer_ar" class="form-control" rows="3" dir="rtl"></textarea></div>
                        <div class="form-group mb-3"><label>Category</label><input type="text" name="category" class="form-control" placeholder="Shipping, Returns, etc."></div>
                        <button type="submit" class="btn btn-success" style="width:100%;">💾 Add FAQ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
