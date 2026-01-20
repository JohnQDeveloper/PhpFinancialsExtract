#!/bin/bash

source .env

# Fetch Dividends
curl -X GET "https://api.massive.com/stocks/v1/dividends?ticker=VTI&limit=100&sort=ex_dividend_date.desc&apiKey=$MASSIVE_API_KEY"

# Fetch EOD
curl -X GET "https://api.massive.com/v1/open-close/VTI/2026-01-16?adjusted=true&apiKey=$MASSIVE_API_KEY"

# Fetch Splits
curl -X GET "https://api.massive.com/stocks/v1/splits?ticker=VTI&limit=100&sort=execution_date.desc&apiKey=$MASSIVE_API_KEY"
