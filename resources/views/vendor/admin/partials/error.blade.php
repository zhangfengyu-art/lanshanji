@if(Session::has('error'))
    @php $error = Session::get('error'); @endphp
    <div class="alert alert-danger alert-dismissable">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        @if(is_string($error))
            <p>{{ $error }}</p>
        @else
            <h4><i class="icon fa fa-ban"></i>{{ array_get($error->get('title'), 0) }}</h4>
            <p>{!! array_get($error->get('message'), 0) !!}</p>
        @endif
    </div>
@elseif (Session::has('errors'))
    @php $errors = Session::get('errors'); @endphp
    @if ($errors->hasBag('error'))
      <div class="alert alert-danger alert-dismissable">

        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        @foreach($errors->getBag("error")->toArray() as $message)
            <p>{!!  array_get($message, 0) !!}</p>
        @endforeach
      </div>
    @endif
@endif
