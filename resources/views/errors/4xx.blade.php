{{-- Any client error without a page of its own (400, 405, 410, 413…): the site's error page with its status. --}}
@extends('errors.layout')

@php($status = isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400)

@section('title', __('errors.4xx.title'))
@section('code', __('errors.4xx.code', ['code' => $status]))
@section('heading', __('errors.4xx.title'))
@section('text', __('errors.4xx.text'))
