<?php

it('returns a successful health check response', function () {
    $this->get('/up')->assertStatus(200);
});
