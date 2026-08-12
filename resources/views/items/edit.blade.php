@extends('layouts.app')
@section('title','Edit item')
@section('content')
<x-item-form :item="$item" :groups="$groups" :editing="true" :has-posted-movements="$hasPostedMovements"/>
@endsection
