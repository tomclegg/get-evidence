php-openid-2.1.3:
	wget -c http://openidenabled.com/files/php-openid/packages/php-openid-2.1.3.tar.bz2
	[ `md5sum php-openid-2.1.3.tar.bz2 | head -c 32` = de51927c576f06d54e4a89665bc32391 ]
	tar xjf php-openid-2.1.3.tar.bz2
