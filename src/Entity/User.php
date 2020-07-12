<?php
/**
 * 2019-06-28.
 */

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Doctrine\ORM\Mapping\JoinTable;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table(name="users", indexes={@Index(columns={"username"})})
 */
final class User
{
    /**
     * @var int|null
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private ?int $id;

    /**
     * @var string|null
     * @ORM\Column(type="string")
     */
    private ?string $username;

    /**
     * @var string|null
     * @ORM\Column(type="string")
     */
    private ?string $password;


    /**
     * @ORM\ManyToMany(targetEntity=Movie::class, inversedBy="likedUsers")
     * @JoinTable(name="users_movies")
     */
    private Collection $likedMovies;

    public function __construct()
    {
        $this->likedMovies = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string|null $username
     *
     * @return User
     */
    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string|null $password
     *
     * @return User
     */
    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function addMovie(Movie $movie): self
    {
        if (!$this->likedMovies->contains($movie)) {
            $this->likedMovies[] = $movie;
            $movie->addUser($this);
        }

        return $this;
    }

    public function removeMovie(Movie $movie): self
    {
        if ($this->likedMovies->contains($movie)) {
            $this->likedMovies->removeElement($movie);
            $movie->removeUser($this);
        }

        return $this;
    }
}
