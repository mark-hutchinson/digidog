<?php

namespace Api;

/** Which login system a player_auth row belongs to. The value is what's stored in player_auth.provider. */
enum AuthProvider: int
{
    case Device = 1;
    case Firebase = 2; // Coming soon
}
