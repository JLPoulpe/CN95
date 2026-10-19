<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\RolePermission;
use App\Repository\RolePermissionRepository;
use App\Repository\RoleRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Matrice rôles × permissions. Réservé aux administrateurs (règle ^/admin) et volontairement indépendant des
 * permissions PERM_* : un mauvais réglage ne doit jamais empêcher de le corriger.
 */
#[Route('/admin/permissions')]
class PermissionController extends AbstractController
{
    #[Route('', name: 'admin_permission_index', methods: ['GET', 'POST'])]
    public function index(Request $request, RoleRepository $roles, RolePermissionRepository $rolePermissions, EntityManagerInterface $em): Response
    {
        // L'administrateur a toujours tous les droits : il n'apparaît pas dans la matrice
        $colonnes = array_values(array_filter(
            $roles->findBy([], ['id' => 'ASC']),
            static fn (Role $role): bool => Role::ADMIN !== $role->getCode(),
        ));

        $existants = [];
        foreach ($rolePermissions->findAll() as $ligne) {
            $existants[$ligne->getRole()->getId().'|'.$ligne->getPermission()] = $ligne;
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('permissions', $request->request->getString('_token'))) {
                $this->addFlash('error', 'Session expirée, merci de réessayer.');

                return $this->redirectToRoute('admin_permission_index');
            }

            $coches = $request->request->all('droits');
            foreach (Permission::toutes() as $permission) {
                foreach ($colonnes as $role) {
                    $cle = $role->getId().'|'.$permission;
                    $voulu = isset($coches[$permission][$role->getId()]);

                    if ($voulu && !isset($existants[$cle])) {
                        $em->persist(new RolePermission($role, $permission));
                    } elseif (!$voulu && isset($existants[$cle])) {
                        $em->remove($existants[$cle]);
                    }
                }
            }
            $em->flush();
            $this->addFlash('success', 'Permissions enregistrées.');

            return $this->redirectToRoute('admin_permission_index');
        }

        return $this->render('admin/permission/index.html.twig', [
            'permissions' => Permission::LIBELLES,
            'roles' => $colonnes,
            'accordees' => array_fill_keys(array_keys($existants), true),
        ]);
    }
}
