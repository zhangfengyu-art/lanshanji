<div class="btn-group" style="margin-left: 10px;">
    <select class="form-control input-sm batch-category-select" style="width: 180px; display: inline-block;">
        <option value="">批量改分类</option>
        @foreach($categories as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </select>
    <button type="button" class="btn btn-sm btn-primary btn-batch-set-category">应用</button>
</div>
