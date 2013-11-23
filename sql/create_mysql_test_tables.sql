DROP TABLE IF EXISTS pet_name;
DROP TABLE IF EXISTS pet_owner;
DROP TABLE IF EXISTS owner;
DROP TABLE IF EXISTS pet;

DROP TABLE IF EXISTS animal_property;
DROP TABLE IF EXISTS animal_property_type;
DROP TABLE IF EXISTS animal_inventory;
DROP TABLE IF EXISTS animal;

-- this data for "animal" and related tables is to demonstrate different types
-- of relationships and data usage that may be found in different technology systems

-- various patterns are used to demonstrate how an ORM works with different stantdards 

-- this is a main animal record
CREATE TABLE animal (
	id				int 			not null		auto_increment		PRIMARY KEY,
	name 			varchar(50) 	not null,
	legs 			int 			not null,
	sound			varchar(50)		not null,
	description		text			null
);

CREATE TABLE animal_inventory (
	animal_id		int			not null		PRIMARY KEY,
	qoh				int			not null,
	last_into_stock	datetime	not null,
	
	INDEX animal_inventory_id (animal_id),
	CONSTRAINT animal_inventory_id
		FOREIGN KEY (animal_id)
		REFERENCES animal (id)
		ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE animal_property_type (
	id		int			not null		auto_increment		PRIMARY KEY,
	name	varchar(20)	not null
);

CREATE TABLE animal_property (
	animal_id			int				not null,
	property_type_id	int				not null,
	set_on_date			datetime		not null,
	comment				varchar(20)		null,
	
	CONSTRAINT animal_property_type
		PRIMARY KEY (animal_id, property_type_id)
);

-- End of "legacy" test patterns

CREATE TABLE pet (
	id			int				not null		auto_increment		PRIMARY KEY,
	name		varchar(50)		not null,
	animal_id	int				not null,
	
	CONSTRAINT pet_animal
		FOREIGN KEY (animal_id)
		REFERENCES animal (id)
		ON DELETE RESTRICT ON UPDATE RESTRICT
);

CREATE TABLE owner (
	id				int				not null		auto_increment		PRIMARY KEY,
	first_name		varchar(50)		not null,
	last_name		varchar(50)		not null,
	address_1		varchar(80)		not null,
	address_2		varchar(80)		not null,
	address_3		varchar(80)		not null,
	city			varchar(50)		not null,
	state			varchar(10)		not null,
	postal_code		varchar(10)		not null,
	phone			varchar(20)		not null
);

CREATE TABLE pet_owner (
	id				int			not null		auto_increment		PRIMARY KEY,
	pet_id			int			not null,
	purchase_dt		datetime	not null,
	owner_id		int			not null
);

CREATE TABLE pet_name (
	id				int				not null		auto_increment		PRIMARY KEY,
	pet_id			int				not null,
	name_dt			int				not null,
	owner_id		int				not null,
	name			varchar(50)		not null,
	
	INDEX pet_name_pet_dt
		(pet_id, name_dt),
	INDEX pet_name_owner_dt
		(owner_id, name_dt),
	CONSTRAINT pet_name_pet_id
		FOREIGN KEY (pet_id)
		REFERENCES pet (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT pet_name_owner_id
		FOREIGN KEY (owner_id)
		REFERENCES owner (id)
		ON DELETE RESTRICT ON UPDATE RESTRICT
);
