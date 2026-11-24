@extends('errors.layout')
@section('code', '403')
@section('title', 'Not allowed')
@section('message', ($exception?->getMessage() ?: '') !== '' && $exception->getMessage() !== 'Forbidden' && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : "Your account doesn't have access to this page. If you think this is a mistake, contact the school administrator.")
