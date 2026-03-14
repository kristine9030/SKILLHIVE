<?php

function analytics_e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
