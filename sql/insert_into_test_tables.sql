INSERT INTO animal
	(name, legs, sound, description)
VALUES
	('bear', 4, 'growl', 'where does the bear go?'),
	('owl', 2, 'hoot', 'a wise old owl'),
	('dog', 4, 'woof', 'man''s best friend'),
	('cow', 4, 'moo', 'moo moo I love you'),
	('chicken', 2, 'bgack', 'are you yellow?'),
	('cat', 4, 'meow', 'mind of its own'),
	('parrot', 2, 'squawk', 'hello polly the pretty parrot'),
	('pig', 4, 'oink', 'rolling around in mud'),
	('crow', 2, 'caw', null);

INSERT INTO animal_inventory
	(animal_id, qoh, last_into_stock)
SELECT id, 17, '2013-02-19 17:45:00' FROM animal WHERE name = 'dog'
UNION
SELECT id, 2, '2013-02-19 17:45:00' FROM animal WHERE name = 'chicken'
UNION
SELECT id, 16, '2013-02-19 17:45:00' FROM animal WHERE name = 'cat'
UNION
SELECT id, 4, '2013-02-19 17:45:00' FROM animal WHERE name = 'parrot'
UNION
SELECT id, 1, '2013-02-19 17:45:00' FROM animal WHERE name = 'pig';

INSERT INTO animal_property_type
    (name)
VALUES
    ('skin'), ('size');

-- Insert into animal property
INSERT INTO animal_property
    (animal_id, property_type_id, set_on_date, comment)
    
SELECT a.id, apt.id, '2013-07-11 09:15:42', 'feathered'
FROM animal a, animal_property_type apt
WHERE a.name In ('owl', 'chicken', 'parrot', 'crow') AND apt.name = 'skin'
UNION
SELECT a.id, apt.id, '2013-07-11 09:15:42', 'fur'
FROM animal a, animal_property_type apt
WHERE a.name In ('bear', 'dog', 'cat') AND apt.name = 'skin'
UNION
SELECT a.id, apt.id, '2013-07-11 09:15:42', 'hide'
FROM animal a, animal_property_type apt
WHERE a.name In ('cow', 'pig') AND apt.name = 'skin'
UNION
SELECT a.id, apt.id, '2013-07-11 09:15:42', 'small'
FROM animal a, animal_property_type apt
WHERE a.name In ('owl', 'chicken', 'parrot', 'crow') AND apt.name = 'size'
UNION
SELECT a.id, apt.id, '2013-07-11 09:15:42', 'large'
FROM animal a, animal_property_type apt
WHERE a.name In ('bear', 'cow') AND apt.name = 'size';
