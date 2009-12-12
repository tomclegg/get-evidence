all: php-openid-2.1.3 textile-2.0.0

php-openid-2.1.3:
	wget -c http://openidenabled.com/files/php-openid/packages/php-openid-2.1.3.tar.bz2
	[ `md5sum php-openid-2.1.3.tar.bz2 | head -c 32` = de51927c576f06d54e4a89665bc32391 ]
	tar xjf php-openid-2.1.3.tar.bz2

textile-2.0.0:
	wget -c http://textile.thresholdstate.com/file_download/2/textile-2.0.0.tar.gz
	[ `md5sum textile-2.0.0.tar.gz | head -c 32` = c4f2454b16227236e01fc1c761366fe3 ]
	tar xzf textile-2.0.0.tar.gz
