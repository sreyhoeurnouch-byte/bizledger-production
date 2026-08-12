@extends('layouts.app')
@section('title','Add item')
@section('content')
<x-item-form :item="$item" :groups="$groups"/>
@endsection
