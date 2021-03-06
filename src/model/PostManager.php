<?php
namespace blogApp\src\model;
use \blogApp\core\File;
/**
 * Class PostManager
 * Model qui gere les posts
 */
class PostManager extends \blogApp\core\Model
{
	function __construct()
	{
		parent::__construct();
		$this->categorieManager = new CategorieManager();
	}

	/**
     * Recupere le nombre de post
     * Retourne la variable $totalPOSTS 
     */
	public function postCount()
	{
		$totalPostsReq = $this->db->query('SELECT COUNT(id) FROM posts');
		$totalPosts = $totalPostsReq->fetch()[0];
		return $totalPosts;
	}

	/**
	 * Recupere tous les posts
	 * Retourne une variable
	 */
	public function getAllPosts()
	{
		$nbPostsPerPage = 10;
		$totalPosts = $this->postCount();
		$totalPages = ceil($totalPosts / $nbPostsPerPage);

		if (isset($_GET['page']) && !empty($_GET['page']) && $_GET['page'] > 0 && $_GET['page'] <= $totalPages) {
			$_GET['page'] = intval($_GET['page']);
			$currentPage = $_GET['page'];
		} else {
			$currentPage = 1;
		}

		$depart = ($currentPage - 1) * $nbPostsPerPage;
		// On récupère les  billets
		$req = $this->db->query('SELECT posts.id, author, title, image_path, post, id_categorie, categories.name, categories.slug, DATE_FORMAT(date_post, \'%d/%m/%Y à %Hh%imin%ss\') AS date_creation_fr FROM posts LEFT JOIN categories ON posts.id_categorie = categories.id ORDER BY date_post DESC LIMIT ' . $depart . ', ' . $nbPostsPerPage);
		$req = $req->fetchAll();

		$varsForPagination = [$req, $totalPages, $currentPage];

		return $varsForPagination;
	}

	/**
	 * Recupere le dernier post
	 * Retourne une variable
	 */
	public function getLastPost()
	{
		// On récupère les 5 derniers billets
		$req = $this->db->query('SELECT posts.id, author, title, image_path, post, id_categorie, categories.name, DATE_FORMAT(date_post, \'%d/%m/%Y à %Hh%imin%ss\') AS date_creation_fr FROM posts LEFT JOIN categories ON posts.id_categorie = categories.id ORDER BY date_post DESC LIMIT 0, 1');
		$req = $req->fetch();

		return $req;
	}

	/**
	 * Recupere les 20 derniers posts
	 * Retourne une variable
	 */
	public function getRecentPosts()
	{
		$req = $this->db->query('SELECT posts.id, author, title, image_path, post, id_categorie, categories.name, categories.slug, DATE_FORMAT(date_post, \'%d/%m/%Y à %Hh%imin%ss\') AS date_creation_fr FROM posts LEFT JOIN categories ON posts.id_categorie = categories.id ORDER BY date_post DESC LIMIT 0, 20');
		$req = $req->fetchAll();

		return $req;
	}

	/**
	 * Recupere 1 post
	 * @param id du post $number
	 * Retourne une variable
	 */
	public function getPost($postId)
	{
	    $req = $this->db->prepare('SELECT posts.id, author,title, image_path, post, id_categorie, image_path, categories.name, categories.slug, DATE_FORMAT(date_post, \'%d/%m/%Y à %Hh%imin%ss\') AS date_creation_fr FROM posts LEFT JOIN categories ON posts.id_categorie = categories.id WHERE posts.id = ?');
	    $req->execute(array($postId));
	    $post = $req->fetch();

	    return $post;
	}

	/**
	 * Creer ou recupere l'id d'une categorie
	 * @param $_post radio du formuaire
	 * Retourne une variable
	 */
	public function getOrCreateCategory($categoryIdOrName)
	{
		if (is_numeric($categoryIdOrName)) {
			return $this->categorieManager->getCategorie($categoryIdOrName);
		}
		return [
			"id" => $this->categorieManager->addNewCategorie($categoryIdOrName)
		];
	}

	/**
	 * Cree un nouveau post 
	 * @param categorie du post $string
	 * @param auteur du post $string
	 * @param titre du post $string
	 * @param contenu du post $string
	 * Retourne une variable
	 */
	public function addNewPost($categoryIdOrName, $author, $title, $post)
	{
		if (!empty($categoryIdOrName) && !empty($author) && !empty($title) && !empty($post) && isset($_SESSION['token']))
		{
			$id_category = $this->getOrCreateCategory($categoryIdOrName)['id'];

			$picturePath = File::uploadImage('picture');

			$newPost = $this->db->prepare('INSERT INTO posts (author, title, post, image_path, id_categorie, date_post) VALUES(?, ?, ?, ?, ?, NOW())');
		    $affectedPost = $newPost->execute(array($author, $title, $post, $picturePath, $id_category));

		    return $affectedPost;
		}
	}

	/**
	 * Modifie un post
	 * @param id ou nom de la categorie $number/$string
	 * @param titre du post $string
	 * @param contenu du post $string
	 * @param id du post $number
	 * Retourne une variable
	 */
	public function updatePost($categoryIdOrName, $title, $post, $idPost)
	{
		$id_category = $this->getOrCreateCategory($categoryIdOrName)['id'];	

		if (isset($_FILES['picture']) && !empty($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
			$req = $this->db->prepare('SELECT image_path FROM posts WHERE id = ?');
			$req->execute(array($idPost));
			$oldPath = $req->fetch();
			$old = str_replace('public/' ,"",$oldPath['image_path']);
			$filePath = File::replaceImage('picture', $old);
		}
		
		if($filePath){
			$newPost = $this->db->prepare('UPDATE posts set title = ?, post = ?, image_path = ?, id_categorie = ? WHERE id = ?');
			$affectedPost = $newPost->execute(array($title, $post, $filePath, $id_category, $idPost));
		} else {
			$newPost = $this->db->prepare('UPDATE posts set title = ?, post = ?, id_categorie = ? WHERE id = ?');
			$affectedPost = $newPost->execute(array($title, $post, $id_category, $idPost));
		}

		return $affectedPost;
	}

	/**
	 * Supprime un post
	 * @param id du post $number
	 * Retourne une variable
	 */
	public function deletePost($idPost)
	{
		$req = $this->db->prepare('SELECT image_path FROM posts WHERE id = ?');
		$req->execute(array($idPost));
		$toDelete = $req->fetch();
		$delete = str_replace('public/' ,"",$toDelete['image_path']);
		File::remove($delete);

		$deletedPost = $this->db->prepare('DELETE FROM posts WHERE posts . id = ?');
		$affectedPost = $deletedPost->execute(array($idPost));

		return $affectedPost;
	}
}