# Project Goals
1) Convert to a CSV with standardized values
2) Pull history for those symbols via Massive for 2 years (Dividends, EOD Values)
3) Store data in a mysql or sqlite database for querying

### Fidelity
Main Positions Page -> Download Positions

### Schwab
Main Positions Page -> All Brokerage Accounts -> Download Positions

### Vanguard
Holdings -> Download Center -> A spreadsheet-compatible CSV File / All Accounts

### Requirements
PHP 8.4.11 (cli) (built: Jan  7 2026 08:44:00) (NTS)

Massive.com API Key in .env (note, unpaid rate limit is low so its not ideal for anything outside of personal use as a batch job, just stick to importing if you don't intend to collect historical data)

##### apt-get installs on Ubuntu 26.04
```bash
apt update
apt install php-curl -y
```
