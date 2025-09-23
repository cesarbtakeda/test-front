CREATE DATABASE login_system;

USE login_system;

CREATE TABLE users (
     id INT AUTO_INCREMENT PRIMARY KEY,
       username VARCHAR(50) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL);

CREATE TABLE users (
     id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL );

CREATE TABLE produtos (
       id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        preco DECIMAL(10,2) NOT NULL,
        categoria VARCHAR(50),
        imagem_url VARCHAR(255));

CREATE TABLE pedidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
       numero_pedido INT NOT NULL,
     nome_cliente VARCHAR(100) NOT NULL,
        data_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
       total DECIMAL(10,2) NOT NULL  );
CREATE TABLE itens_pedido (
       id INT AUTO_INCREMENT PRIMARY KEY,
      pedido_id INT NOT NULL,
       produto_id INT NOT NULL,
       quantidade INT NOT NULL,
         preco_unitario DECIMAL(10,2) NOT NULL,
         FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
         FOREIGN KEY (produto_id) REFERENCES produtos(id) );
