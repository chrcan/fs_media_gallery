#
# Table structure for table 'sys_file_collection'
#
CREATE TABLE sys_file_collection
(
	slug           varchar(255) DEFAULT '' NOT NULL,
	datetime       int(11)      DEFAULT 0 NOT NULL,
	parentalbum    int(11)      DEFAULT 0 NOT NULL,
	main_asset     int(11)      DEFAULT 0 NOT NULL,
	webdescription text,
	sorting        int(11)      DEFAULT 0 NOT NULL,

	KEY            parentalbum (parentalbum)
);
