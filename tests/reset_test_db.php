<?php
@unlink(__DIR__."/test.db");
$pdo=new PDO("sqlite:".__DIR__."/test.db"); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents(__DIR__."/schema_sqlite.sql"));
$seed=preg_replace("/^--.*$/m","",file_get_contents(dirname(__DIR__)."/sql/seed.sql")); $seed=str_replace("SET NAMES utf8mb4;","",$seed);
foreach(array_filter(array_map("trim",explode(";\n",$seed))) as $st){ $pdo->query($st); }
$pdo->exec("INSERT INTO users(role,full_name,email,phone,password_hash) VALUES ('admin','Mohamad Mafras','admin@example.com',NULL,'".password_hash("admin123",PASSWORD_DEFAULT)."')");
echo "reset\n";
