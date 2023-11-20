<?php

include "token.php";

class LexToken {
    public function __construct(
        public Token $token, 
        public mixed $contents = null
    ) {}
}

function token(Token $token): LexToken {
    return new LexToken($token);
}

function tokenWith(Token $token, mixed $contents): LexToken {
    return new LexToken($token, $contents);
}
