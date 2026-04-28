<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channel — siapa saja bisa subscribe
Broadcast::channel('workflow-run.{runId}', function ($user, $runId) {
    return true; // nanti bisa ditambah validasi tenant
});