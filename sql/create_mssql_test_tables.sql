IF OBJECT_ID('pet_name', 'U') IS NOT NULL DROP TABLE pet_name;
IF OBJECT_ID('pet_owner', 'U') IS NOT NULL DROP TABLE pet_owner;
IF OBJECT_ID('owner', 'U') IS NOT NULL DROP TABLE owner;
IF OBJECT_ID('pet', 'U') IS NOT NULL DROP TABLE pet;

IF OBJECT_ID('animal_property', 'U') IS NOT NULL DROP TABLE animal_property;
IF OBJECT_ID('animal_property_type', 'U') IS NOT NULL DROP TABLE animal_property_type;
IF OBJECT_ID('animal_inventory', 'U') IS NOT NULL DROP TABLE animal_inventory;
IF OBJECT_ID('animal', 'U') IS NOT NULL DROP TABLE animal;

-- this data for "animal" and related tables is to demonstrate different types
-- of relationships and data usage that may be found in different technology systems

-- various patterns are used to demonstrate how an ORM works with different stantdards 

-- this is a main animal record
CREATE TABLE animal (
	id				int 			not null		identity		PRIMARY KEY,
	name 			varchar(50) 	not null,
	legs 			int 			not null,
	sound			varchar(50)		not null,
	description		text			null
);

CREATE TABLE animal_inventory (
	animal_id		int			not null		PRIMARY KEY,
	qoh				int			not null,
	last_into_stock	datetime	not null,
	
	CONSTRAINT animal_inventory_id
		FOREIGN KEY (animal_id)
			REFERENCES animal (id)
			ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE animal_property_type (
	id		int			not null		identity		PRIMARY KEY,
	name	varchar(20)	not null
);

CREATE TABLE animal_property (
	animal_id			int				not null,
	property_type_id	int				not null,
	set_on_date			datetime		not null,
	comment				varchar(20)		null,
	
	CONSTRAINT animal_property_animal_property_type
		PRIMARY KEY (animal_id, property_type_id)
);


-- End of "legacy" test patterns

CREATE TABLE pet (
	id			int				not null		identity		PRIMARY KEY,
	name		varchar(50)		not null,
	animal_id	int				not null,
	
	CONSTRAINT pet_animal
		FOREIGN KEY (animal_id)
		REFERENCES animal (id)
		ON DELETE NO ACTION ON UPDATE NO ACTION
);

CREATE TABLE owner (
	id				int				not null		identity		PRIMARY KEY NONCLUSTERED,
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

CREATE CLUSTERED INDEX owner_last_name_first_name
	ON owner (last_name, first_name);

CREATE TABLE pet_owner (
	id				int			not null		identity		PRIMARY KEY NONCLUSTERED,
	pet_id			int			not null,
	purchase_dt		datetime	not null,
	owner_id		int			not null
);

CREATE CLUSTERED INDEX pet_owner_pet_purchase_dt
	ON pet_owner (pet_id, purchase_dt);

CREATE NONCLUSTERED INDEX pet_owner_owner_purchase_dt
	ON pet_owner (owner_id, purchase_dt);

CREATE TABLE pet_name (
	id				int				not null		identity		PRIMARY KEY NONCLUSTERED,
	pet_id			int				not null,
	name_dt			int				not null,
	owner_id		int				not null,
	name			varchar(50)		not null,
	
	CONSTRAINT pet_name_pet_id
		FOREIGN KEY (pet_id)
		REFERENCES pet (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT pet_name_owner_id
		FOREIGN KEY (owner_id)
		REFERENCES owner (id)
		ON DELETE NO ACTION ON UPDATE NO ACTION
);

CREATE CLUSTERED INDEX pet_name_pet_dt
	ON pet_name (pet_id, name_dt);

CREATE NONCLUSTERED INDEX pet_name_owner_dt
	ON pet_name (owner_id, name_dt);
