<?php

namespace ECidade\Package\Desktop\Model;

use \ECidade\V3\Extension\Model;
use \Exception;

class Window extends Model
{

  /**
   * @param integer $iUsuario
   * @param integer $iInstituicao
   * @param string $sData
   * @param integer $iLimit
   * @return array
   */
  public function getDepartamentos($iUsuario, $iInstituicao, $sData, $iLimit = null)
  {

    $sSql  = "  select distinct d.coddepto, d.descrdepto, u.db17_ordem   ";
    $sSql .= "    from db_depusu u                                       ";
    $sSql .= "         inner join db_depart d on u.coddepto = d.coddepto ";
    $sSql .= "   where instit       = $iInstituicao ";
    $sSql .= "     and u.id_usuario = $iUsuario";
    $sSql .= "     and (d.limite is null or d.limite >= '$sData')";
    $sSql .= "order by u.db17_ordem ";

    if (!empty($iLimit)) {
      $sSql .= " limit $iLimit ";
    }

    $rsDepartamentos = $this->db->execute($sSql);

    if (pg_num_rows($rsDepartamentos) == 0) {
      return array();
    }

    return $this->db->getCollectionByRecord($rsDepartamentos);
  }

  /**
   * @param integer $iUsuario
   * @param integer $iInstituicao
   * @param string $sData
   * @param integer $iLimit
   * @return stdClass
   */
  public function getDepartamento($iDepartamento, $iUsuario, $iInstituicao, $sData, $iLimit = null)
  {

    $sSql  = "  select distinct d.coddepto, d.descrdepto, u.db17_ordem   ";
    $sSql .= "    from db_depusu u                                       ";
    $sSql .= "         inner join db_depart d on u.coddepto = d.coddepto ";
    $sSql .= "   where d.coddepto = $iDepartamento                       ";
    $sSql .= "     and instit       = $iInstituicao ";
    $sSql .= "     and u.id_usuario = $iUsuario";
    $sSql .= "     and (d.limite is null or d.limite >= '$sData')";

    $rsDepartamentos = $this->db->execute($sSql);

    if (pg_num_rows($rsDepartamentos) == 0) {
      return false;
    }

    return $this->db->fetchRow($rsDepartamentos, 0);
  }

  /**
   * @param integer $codigo
   * @return string
   */
  public function getNomeInstituicao($codigo)
  {

    $sSql = "select nomeinst from db_config where codigo = $codigo";
    $rsInstiuicao = $this->db->execute($sSql);

    if (pg_num_rows($rsInstiuicao) == 0) {
      return null;
    }

    return $this->db->fetchRow($rsInstiuicao, 0)->nomeinst;
  }

  /**
   * @param integer $codigo
   * @return string
   */
  public function getNomeDepartamento($codigo)
  {

    $sSql  = "select descrdepto from db_depart where coddepto = $codigo";
    $result = $this->db->execute($sSql);

    if (pg_num_rows($result) == 0) {
      return null;
    }

    return $this->db->fetchRow($result, 0)->descrdepto;
  }

  /**
   * @param integer $idUsuario
   * @param array
   */
  public function getExercicios($idUsuario)
  {

    if ($idUsuario == 1) {

      $sSql  = "select anousu                  ";
      $sSql .= "  from db_permissao            ";
      $sSql .= " where id_usuario = $idUsuario ";
      $sSql .= "group by id_usuario, anousu    ";
      $sSql .= "order by anousu desc           ";
    } else {

      $sSql  = " select distinct on (anousu) anousu                                                 ";
      $sSql .= "   from (select id_usuario, anousu                                                  ";
      $sSql .= "           from db_permissao                                                        ";
      $sSql .= "          where id_usuario = $idUsuario                                             ";
      $sSql .= "       group by id_usuario, anousu                                                  ";
      $sSql .= "       union all                                                                    ";
      $sSql .= "         select db_permissao.id_usuario, anousu                                     ";
      $sSql .= "           from db_permissao                                                        ";
      $sSql .= "                inner join db_permherda h on h.id_perfil  = db_permissao.id_usuario ";
      $sSql .= "                inner join db_usuarios  u on u.id_usuario = h.id_perfil             ";
      $sSql .= "                                         and u.usuarioativo = '1'                   ";
      $sSql .= "          where h.id_usuario = $idUsuario                                           ";
      $sSql .= "         group by db_permissao.id_usuario, anousu                                   ";
      $sSql .= "         ) as x                                                                     ";
      $sSql .= "order by anousu desc                                                                ";
    }

    $result = $this->db->execute($sSql);

    if (pg_num_rows($result) == 0) {
      throw new Exception("Você n�o tem permissão de acesso para exercício.");
    }

    $exercicios = array();
    foreach ($this->db->getCollectionByRecord($result) as $data) {
      $exercicios[] = $data['anousu'];
    }

    return $exercicios;
  }

  /**
   * @param integer $usuario
   * @return string
   */
  public function getDataUsuario($usuario)
  {

    $sql = "select data from db_datausuarios where id_usuario = $usuario";
    $result = $this->db->execute($sql);

    if (pg_num_rows($result) == 0) {
      return false;
    }

    return $this->db->fetchRow($result, 0)->data;
  }

  /**
   * @param integer $idUsuario
   * @param string $data - formato Y-m-d
   * @return void
   */
  public function salvarDataUsuario($idUsuario, $data)
  {

    $this->db->begin();
    $this->excluirDataUsuario($idUsuario);

    if ($data != date('Y-m-d')) {
      $this->db->execute("INSERT INTO db_datausuarios( id_usuario, data ) VALUES ({$idUsuario}, '{$data}')");
    }

    $this->db->commit();
  }

  /**
   * @param integer $idUsuario
   */
  public function excluirDataUsuario($idUsuario)
  {

    $this->db->begin();
    $this->db->execute("DELETE FROM db_datausuarios WHERE id_usuario = $idUsuario");
    $this->db->commit();
  }

  /**
   * @param integer $idModulo
   */
  public function getNotificacao($iUsuario, $idModulo, $iInstituicao, $sData, $iDepartamento)
  {
    if ($idModulo == 381) {
      $sql = "SELECT DISTINCT liclicita.l20_codigo, liclicita.l20_edital, liclicita.l20_nroedital, liclicita.l20_anousu, pctipocompra.pc50_descr, liclicita.l20_numero, pctipocompra.pc50_pctipocompratribunal, liclicita.l20_objeto, liclicita.l20_naturezaobjeto dl_Natureza_objeto, (CASE WHEN l03_pctipocompratribunal in (48, 49, 50, 52, 53, 54) and liclicita.l20_dtpublic is not null THEN liclicita.l20_dtpublic WHEN l03_pctipocompratribunal in (100, 101, 102, 103, 106) and liclicita.l20_datacria is not null THEN liclicita.l20_datacria WHEN liclancedital.l47_dataenvio is not null THEN liclancedital.l47_dataenvio END) as dl_Data_Referencia, l10_descr as status FROM liclicita INNER JOIN db_config ON db_config.codigo = liclicita.l20_instit INNER JOIN db_usuarios ON db_usuarios.id_usuario = liclicita.l20_id_usucria INNER JOIN cflicita ON cflicita.l03_codigo = liclicita.l20_codtipocom INNER JOIN liclocal ON liclocal.l26_codigo = liclicita.l20_liclocal INNER JOIN liccomissao ON liccomissao.l30_codigo = liclicita.l20_liccomissao INNER JOIN licsituacao ON licsituacao.l08_sequencial = liclicita.l20_licsituacao INNER JOIN cgm ON cgm.z01_numcgm = db_config.numcgm INNER JOIN db_config AS dbconfig ON dbconfig.codigo = cflicita.l03_instit INNER JOIN pctipocompra ON pctipocompra.pc50_codcom = cflicita.l03_codcom INNER JOIN bairro ON bairro.j13_codi = liclocal.l26_bairro INNER JOIN ruas ON ruas.j14_codigo = liclocal.l26_lograd LEFT JOIN liclicitaproc ON liclicitaproc.l34_liclicita = liclicita.l20_codigo LEFT JOIN protprocesso ON protprocesso.p58_codproc = liclicitaproc.l34_protprocesso LEFT JOIN liclicitem ON liclicita.l20_codigo = l21_codliclicita LEFT JOIN acordoliclicitem ON liclicitem.l21_codigo = acordoliclicitem.ac24_liclicitem LEFT JOIN pcprocitem ON pcprocitem.pc81_codprocitem = liclicitem.l21_codpcprocitem LEFT JOIN pcproc ON pcproc.pc80_codproc = pcprocitem.pc81_codproc LEFT JOIN liclancedital on liclancedital.l47_liclicita = liclicita.l20_codigo INNER JOIN editalsituacao on editalsituacao.l10_sequencial = liclicita.l20_cadinicial WHERE l20_instit = $iInstituicao and (l10_descr = 'AGUARDANDO ENVIO' OR l10_descr = 'PENDENTE') AND (CASE WHEN l03_pctipocompratribunal IN (48, 49, 50, 52, 53, 54) AND liclicita.l20_dtpublic IS NOT NULL THEN EXTRACT(YEAR FROM liclicita.l20_dtpublic) WHEN l03_pctipocompratribunal IN (100, 101, 102, 103, 106) AND liclicita.l20_datacria IS NOT NULL THEN EXTRACT(YEAR FROM liclicita.l20_datacria) END) >= 2020 AND liclicita.l20_naturezaobjeto in (1, 7) AND (select count(l21_codigo) from liclicitem where l21_codliclicita = liclicita.l20_codigo) >= 1 ORDER BY l20_codigo";
      $result = $this->db->execute($sql);
      if (pg_num_rows($result) == 0) {
        return false;
      }
      return true;
    } elseif ($idModulo == 480) {
      $sql = "select m64_matmater, m60_descr, coalesce(m70_quant, 0) as m70_quant, m64_pontopedido, case when (coalesce(m70_quant,0) - coalesce(m64_pontopedido,0)) > 0 then ('FALTAM '||(coalesce(m70_quant,0) - coalesce(m64_pontopedido,0)))::VARCHAR else 'ATINGIU P. PEDIDO'::VARCHAR end as dif_ponto_pedido from matmaterestoque inner join matmater on matmater.m60_codmater = matmaterestoque.m64_matmater inner join db_almox on db_almox.m91_codigo = matmaterestoque.m64_almox inner join db_depart on db_depart.coddepto = db_almox.m91_depto inner join matestoque on matestoque.m70_codmatmater = matmater.m60_codmater and matestoque.m70_coddepto = db_almox.m91_depto where 1 = 1 order by m91_codigo, m64_matmater";
      $result = $this->db->execute($sql);
      if (pg_num_rows($result) == 0) {
        return false;
      }
      return true;
    } elseif ($idModulo == 8251) {
      $sql = "select distinct acordo.ac16_sequencial, CASE WHEN ac16_semvigencia='t' THEN ('-')::varchar ELSE (ac16_numeroacordo || '/' || ac16_anousu)::varchar END dl_N�_Acordo, contratado.z01_numcgm, contratado.z01_nome, acordo.ac16_resumoobjeto::text, CASE WHEN ac16_semvigencia='t' THEN null ELSE ac16_datainicio END ac16_datainicio, CASE WHEN ac16_semvigencia='t' THEN null ELSE ac16_datafim END ac16_datafim, CAST(ac16_datafim AS date) - date '$sData' ||' dias' as dl_Prazo, (CASE WHEN ac16_providencia is null THEN 'Pendente' ELSE providencia.descricao END) as dl_Providencia from acordo inner join cgm contratado on contratado.z01_numcgm = acordo.ac16_contratado inner join db_depart on db_depart.coddepto = acordo.ac16_coddepto inner join db_depart depresp on depresp.coddepto = acordo.ac16_deptoresponsavel inner join acordogrupo on acordogrupo.ac02_sequencial = acordo.ac16_acordogrupo inner join acordosituacao on acordosituacao.ac17_sequencial = acordo.ac16_acordosituacao left join acordocomissao on acordocomissao.ac08_sequencial = acordo.ac16_acordocomissao inner join acordonatureza on acordonatureza.ac01_sequencial = acordogrupo.ac02_acordonatureza inner join acordotipo on acordotipo.ac04_sequencial = acordogrupo.ac02_acordotipo inner join acordoorigem on acordoorigem.ac28_sequencial = acordo.ac16_origem left join acordoleis on acordo.ac16_lei = acordoleis.ac54_sequencial LEFT JOIN ( select max(ac26_sequencial) as ac26_sequencial,ac26_acordo,ac26_data from acordoposicao GROUP BY ac26_acordo, ac26_data ) acordoposicao ON acordoposicao.ac26_acordo = ac16_sequencial LEFT JOIN acordoitem on ac20_acordoposicao=ac26_sequencial LEFT JOIN acordopcprocitem on ac23_acordoitem=ac20_sequencial LEFT JOIN pcprocitem on pc81_codprocitem=ac23_pcprocitem LEFT JOIN solicitem on pc11_codigo=pc81_solicitem LEFT JOIN solicita on pc10_numero=pc11_numero LEFT JOIN solicitemvinculo on pc55_solicitemfilho=pc11_codigo LEFT JOIN solicitem pai on pai.pc11_numero = pc55_solicitempai LEFT JOIN pcprocitem pclic on pclic.pc81_solicitem=pai.pc11_codigo LEFT JOIN liclicitem on l21_codpcprocitem=pclic.pc81_codprocitem LEFT JOIN liclicita on l20_codigo=l21_codliclicita LEFT JOIN cflicita on l03_codigo=l20_codtipocom LEFT JOIN pctipocompra on pc50_codcom=l03_codcom LEFT JOIN providencia on providencia.codigo=ac16_providencia where cast((case WHEN ac16_datafim > ac26_data THEN ac16_datafim ELSE ac26_data END) AS date) - date '$sData' between 0 and 60 and ac16_instit = $iInstituicao and (ac16_providencia is null OR ac16_providencia in (2)) and ac16_acordosituacao = 4 order by ac16_datafim";
      $result = $this->db->execute($sql);
      if (pg_num_rows($result) == 0) {
        return false;
      }
      return true;
    } elseif ($idModulo == 604) {
      $sql = "select distinct p62_dttran, descrdepto, p51_descr as p58_codigo, array_to_string( array_agg( p58_numero||'/'||p58_ano ), ', ') as p63_codproc, p58_requer, login as dl_Usu�rio, p62_codtran from proctransferproc inner join proctransfer on p62_codtran = p63_codtran inner join protprocesso on p58_codproc = p63_codproc inner join tipoproc on p58_codigo = p51_codigo inner join db_depart on coddepto = p62_coddepto inner join db_usuarios on id_usuario = p62_id_usuario left join proctransand on p64_codtran = p62_codtran left join arqproc on p68_codproc = p63_codproc where ( p62_id_usorec = $iUsuario or p62_id_usorec = 0 ) and p62_coddeptorec = $iDepartamento and p64_codtran is null and p68_codproc is null group by p62_dttran, descrdepto, p58_numeracao, p51_descr, p63_codproc, p58_requer, login, p62_codtran order by p62_codtran desc";
      $result = $this->db->execute($sql);
      if (pg_num_rows($result) == 0) {
        return false;
      }
      return true;
    }
  }
}
